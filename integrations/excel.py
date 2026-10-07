import sys, json, base64, zipfile, io, re, datetime
import xml.etree.ElementTree as ET
N = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'
ET.register_namespace('', N)
def tag(s): return '{'+N+'}'+s
def xml(z, p):
    b=z.read(p)
    if b'<!DOCTYPE' in b or b'<!ENTITY' in b: raise ValueError('XML não permitido.')
    return ET.fromstring(b)
def column(ref): return re.match('[A-Z]+', ref).group()
def colnum(s):
    n=0
    for c in s: n=n*26+ord(c)-64
    return n
def colname(n):
    s=''
    while n: n,r=divmod(n-1,26); s=chr(65+r)+s
    return s

def run(d):
    raw=base64.b64decode(d['contents'], validate=True)
    if len(raw)>10*1024*1024: raise ValueError('Arquivo maior que 10 MB.')
    z=zipfile.ZipFile(io.BytesIO(raw)); infos=z.infolist()
    if len(infos)>2000 or sum(i.file_size for i in infos)>80*1024*1024: raise ValueError('Planilha excede os limites de processamento.')
    book=xml(z,'xl/workbook.xml'); rels=xml(z,'xl/_rels/workbook.xml.rels')
    paths={r.get('Id'):r.get('Target') for r in rels}
    sheets=[]
    for s in book.find(tag('sheets')):
        p=paths[s.get('{'+R+'}id')]
        p=p.lstrip('/') if p.startswith('/') else 'xl/'+p
        if '..' in p.split('/'): raise ValueError('Caminho inválido.')
        sheets.append((s.get('name'),p))
    name=d.get('sheet') or sheets[0][0]
    if name not in dict(sheets): raise ValueError('Selecione uma aba válida.')
    path=dict(sheets)[name]; tree=xml(z,path); data=tree.find(tag('sheetData'))
    namespaces=dict((prefix,uri) for _,(prefix,uri) in ET.iterparse(io.BytesIO(z.read(path)), events=('start-ns',)))
    for prefix,uri in namespaces.items():
        if not re.match(r'ns[0-9]+$',prefix): ET.register_namespace(prefix,uri)
    shared=[]
    if 'xl/sharedStrings.xml' in z.namelist():
        shared=[''.join(t.text or '' for t in si.iter(tag('t'))) for si in xml(z,'xl/sharedStrings.xml')]
    dates=set()
    if 'xl/styles.xml' in z.namelist():
        styles=xml(z,'xl/styles.xml'); formats={int(f.get('numFmtId')):f.get('formatCode','') for f in styles.findall('./'+tag('numFmts')+'/'+tag('numFmt'))}
        for i,x in enumerate(styles.find(tag('cellXfs')) or []):
            fmt=int(x.get('numFmtId','0'))
            if fmt in list(range(14,23))+list(range(45,48)) or re.search(r'[dy]',re.sub(r'"[^"]*"','',formats.get(fmt,'')).lower()): dates.add(i)
    epoch=datetime.datetime(1904,1,1) if book.find(tag('workbookPr')) is not None and book.find(tag('workbookPr')).get('date1904') in ('1','true') else datetime.datetime(1899,12,30)
    def val(c):
        v=c.find(tag('v')); text=v.text if v is not None else ''
        if c.get('t')=='s': return shared[int(text)]
        if c.get('t')=='inlineStr': return ''.join(t.text or '' for t in c.iter(tag('t')))
        if text and int(c.get('s','0')) in dates:
            try: return (epoch+datetime.timedelta(days=float(text))).isoformat()
            except ValueError: pass
        return text or ''
    rows=[]
    for r in data:
        values={column(c.get('r')):val(c) for c in r if c.tag==tag('c')}
        if any(values.values()): rows.append({'row':int(r.get('r')), 'values':values})
    if len(rows)>10001: raise ValueError('Limite de 10.000 linhas por importação.')
    header=int(d.get('header_row',1))
    headers=next((r['values'] for r in rows if r['row']==header),{})
    if d['action']=='read': return {'sheet':name,'sheets':[s[0] for s in sheets], 'headers':headers,'rows':[r for r in rows if r['row']>header]}
    columns=d.get('mapping',{})
    existing=max([colnum(c.get('r').rstrip('0123456789')) for r in data for c in r if c.tag==tag('c')]+[0])
    targets={}
    titles={'responsible':'FacilitaJud Responsável','status':'FacilitaJud Situação','note':'FacilitaJud Andamento','updated':'FacilitaJud Atualizado em'}
    for key,title in titles.items():
        # Dedicated output columns preserve all original cells, formulas and formatting.
        found=next((c for c,t in headers.items() if t==title),None)
        if not found: existing+=1; found=colname(existing)
        targets[key]=found
    rowmap={int(r.get('r')):r for r in data}
    def put(n,c,v):
        r=rowmap.get(n)
        if r is None: r=ET.SubElement(data,tag('row'),{'r':str(n)}); rowmap[n]=r
        cell=next((x for x in r if x.get('r')==c+str(n)),None)
        if cell is None: cell=ET.SubElement(r,tag('c'),{'r':c+str(n)})
        cell.clear(); cell.set('r',c+str(n)); cell.set('t','inlineStr')
        t=ET.SubElement(ET.SubElement(cell,tag('is')),tag('t')); t.text=str(v or '')
    for key,c in targets.items(): put(header,c,titles[key])
    for item in d['items']:
        for key,c in targets.items(): put(item['source_row'],c,item.get(key,''))
    for r in data: r[:]=sorted(r,key=lambda c:colnum(column(c.get('r'))))
    dim=tree.find(tag('dimension'))
    if dim is not None: dim.set('ref','A1:'+colname(existing)+str(max(rowmap)))
    serialized=ET.tostring(tree,encoding='unicode',xml_declaration=False)
    rootend=serialized.index('>')
    missing=''.join(' xmlns'+(':'+prefix if prefix else '')+'="'+uri+'"' for prefix,uri in namespaces.items() if ('xmlns'+(':'+prefix if prefix else '')+'=') not in serialized[:rootend])
    serialized=(serialized[:rootend]+missing+serialized[rootend:]).encode('utf-8')
    out=io.BytesIO()
    with zipfile.ZipFile(out,'w',zipfile.ZIP_DEFLATED) as dest:
        for info in infos: dest.writestr(info,serialized if info.filename==path else z.read(info.filename))
    return {'contents':base64.b64encode(out.getvalue()).decode()}
try:
    print(json.dumps(run(json.load(sys.stdin)),ensure_ascii=True))
except Exception as e:
    print(json.dumps({'error':str(e)},ensure_ascii=True)); sys.exit(1)
