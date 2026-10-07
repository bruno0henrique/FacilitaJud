import test from 'node:test';
import assert from 'node:assert/strict';
import { AudioUploadQueue, setupMeetingRecorder, setupMeetingModule } from '../resources/js/meeting-recorder.js';

test('splits audio into small ordered chunks and releases sent data', async () => {
    const received = []; const queue = new AudioUploadQueue(async chunk => received.push([chunk.sequence, chunk.blob.size]));
    await queue.append(new Blob([new Uint8Array(600000)]));
    assert.deepEqual(received, [[0,262144],[1,262144],[2,75712]]);
    assert.equal(queue.savedBytes,600000); assert.equal(queue.sentCount,3); assert.equal(queue.pending.length,0);
});

test('retains unsent audio and retries with the same sequence after failure', async () => {
    let fail = true; const received = []; const queue = new AudioUploadQueue(async chunk => { if (fail) throw new Error('offline'); received.push(chunk.sequence); });
    await assert.rejects(queue.append(new Blob(['first'])), /offline/);
    await assert.rejects(queue.append(new Blob(['second'])), /offline/);
    assert.equal(queue.pending.length,2); assert.equal(queue.sentCount,0);
    fail = false; await queue.flush();
    assert.deepEqual(received,[0,1]); assert.equal(queue.pending.length,0); assert.equal(queue.savedBytes,11);
});

test('serializes simultaneous data events without duplicate uploads', async () => {
    const received=[]; let unblock; const blocked = new Promise(resolve => unblock=resolve);
    const queue = new AudioUploadQueue(async chunk => { await blocked; received.push(chunk.sequence); });
    const first=queue.append(new Blob(['one'])); const second=queue.append(new Blob(['two'])); unblock();
    await Promise.all([first,second]); assert.deepEqual(received,[0,1]); assert.equal(queue.sentCount,2);
});

test('recording window saves the final audio before enabling playback and protects unsaved capture', async () => {
    const originals = Object.fromEntries(['window','document','navigator','MediaRecorder'].map(key => [key, Object.getOwnPropertyDescriptor(globalThis,key)]));
    const handlers = new Map(); const windowEvents = new Map(); const calls = []; let microphoneStopped = false; let recorder;
    const node = () => ({hidden:true,disabled:false,textContent:'',classList:{add(){}},querySelectorAll(){return [];},addEventListener(name, handler){this[name]=handler;},scrollIntoView(){}});
    const ids = ['meeting-recording-window','meeting-start-form','meeting-recorder','meeting-recording-state','meeting-live-notice','meeting-recording-error','meeting-retry','meeting-stop','meeting-timer','meeting-upload-status','meeting-close-window','meeting-saved-audio','meeting-saved-actions','meeting-recording-dot','meeting-recording-title','meeting-participants'];
    const nodes = Object.fromEntries(ids.map(id=>['#'+id,node()]));
    nodes['#meeting-recording-window'].dataset = {meetingId:'42',meetingTitle:'Reunião de teste'};
    const submit = node(), error = node(); const form = nodes['#meeting-start-form'];
    form.querySelector = selector => selector === 'button.primary' ? submit : error;
    form.elements = {participants_confirmed:{checked:true},participants:{value:'Pessoa de teste'}};
    const track = {stop(){microphoneStopped=true;},addEventListener(){}};
    class FakeRecorder {
        static isTypeSupported(){return true;}
        constructor(){recorder=this;this.state='inactive';this.mimeType='audio/webm;codecs=opus';}
        addEventListener(type, callback){handlers.set(type,callback);}
        start(){this.state='recording';}
        stop(){this.state='inactive';handlers.get('dataavailable')({data:new Blob(['synthetic-audio'])});handlers.get('stop')();}
    }
    try {
        Object.defineProperty(globalThis,'window',{configurable:true,value:{isSecureContext:true,MediaRecorder:FakeRecorder,close(){},addEventListener(type,cb){windowEvents.set(type,cb);}}});
        Object.defineProperty(globalThis,'document',{configurable:true,value:{querySelector:selector=>nodes[selector]}});
        Object.defineProperty(globalThis,'navigator',{configurable:true,value:{mediaDevices:{async getUserMedia(){return {getTracks:()=>[track],getAudioTracks:()=>[track]};}}}});
        Object.defineProperty(globalThis,'MediaRecorder',{configurable:true,value:FakeRecorder});
        setupMeetingRecorder({toast(){},async api(url,{data}){calls.push({url,data});return url.endsWith('/finish') ? {id:7,status:'ready',size:15,audio_url:'/reunioes/audio/7'} : {id:7};}});
        await form.submit({preventDefault(){}});
        assert.equal(recorder.state,'recording'); assert.equal(form.hidden,true); assert.equal(nodes['#meeting-participants'].textContent,'Pessoa de teste');
        let protectedExit=false; windowEvents.get('beforeunload')({preventDefault(){protectedExit=true;}}); assert.equal(protectedExit,true);
        nodes['#meeting-stop'].click();
        for (let i=0;i<20 && nodes['#meeting-recording-state'].textContent!=='Gravação salva';i++) await new Promise(resolve=>setTimeout(resolve,5));
        assert.equal(microphoneStopped,true); assert.equal(nodes['#meeting-recording-state'].textContent,'Gravação salva');
        assert.equal(nodes['#meeting-saved-audio'].src,'/reunioes/audio/7'); assert.equal(nodes['#meeting-saved-audio'].hidden,false);
        assert.deepEqual(calls.map(call=>call.url),['/api/v1/meetings/42/recordings','/api/v1/meeting-recordings/7/chunks','/api/v1/meeting-recordings/7/finish']);
        assert.equal(calls[1].data.get('sequence'),'0'); assert.equal(calls[2].data.chunks,1);
        protectedExit=false; windowEvents.get('beforeunload')({preventDefault(){protectedExit=true;}}); assert.equal(protectedExit,false);
    } finally { for (const [key, descriptor] of Object.entries(originals)) { if(descriptor) Object.defineProperty(globalThis,key,descriptor); else delete globalThis[key]; } }
});

test('saved notification updates the meeting list and preserves unsaved notes', async () => {
    const originals = Object.fromEntries(['window','document'].map(key => [key,Object.getOwnPropertyDescriptor(globalThis,key)]));
    let channel, dirty=false, revision='first'; const notices=[]; const listeners={}; const count={textContent:''};
    const container={dataset:{},innerHTML:'old-list',querySelector:()=>dirty?{}:null,querySelectorAll:()=>[]};
    const item={dataset:{meetingId:'42'},open:true,addEventListener(){},querySelector:selector=>selector==='[data-meeting-recordings]'?container:count};
    const module={dataset:{consented:'1',syncChannel:'test-meetings'},querySelectorAll:selector=>selector.startsWith('[data-meeting-id]')?[item]:[],querySelector:()=>item};
    const noOp={addEventListener(){},showModal(){}};
    class Channel { constructor(){channel=this;} addEventListener(type,cb){this[type]=cb;}close(){} }
    try {
        Object.defineProperty(globalThis,'document',{configurable:true,value:{visibilityState:'visible',querySelector:selector=>selector==='#meetings-module'?module:noOp,querySelectorAll:()=>[],addEventListener(){}}});
        Object.defineProperty(globalThis,'window',{configurable:true,value:{BroadcastChannel:Channel,addEventListener(type,cb){listeners[type]=cb;}}});
        setupMeetingModule({toast:text=>notices.push(text),async api(url){assert.equal(url,'/api/v1/meetings/42/recordings');return {count:revision==='first'?1:2,revision,html:'saved-list-'+revision};}});
        channel.message({data:{type:'recording-saved',meetingId:42}});
        await new Promise(resolve=>setTimeout(resolve,0));
        assert.equal(count.textContent,'1 gravação');assert.equal(container.innerHTML,'saved-list-first');assert.equal(item.open,true);
        dirty=true;revision='second';channel.message({data:{type:'recording-saved',meetingId:42}});
        await new Promise(resolve=>setTimeout(resolve,0));
        assert.equal(count.textContent,'2 gravações');assert.equal(container.innerHTML,'saved-list-first');assert.match(notices.at(-1),/Salve suas anotações/);
        dirty=false;listeners.focus();await new Promise(resolve=>setTimeout(resolve,0));assert.equal(container.innerHTML,'saved-list-second');
        listeners.pagehide();
    } finally {for(const [key,descriptor] of Object.entries(originals)){if(descriptor)Object.defineProperty(globalThis,key,descriptor);else delete globalThis[key];}}
});
