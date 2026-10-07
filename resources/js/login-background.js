// Wave noise and Bayer dithering adapted from the supplied Dither component.
const fragmentSource = `#version 300 es

precision highp float;
out vec4 fragmentColor;
uniform vec2 resolution;
uniform float time;
uniform float waveSpeed;
uniform float waveFrequency;
uniform float waveAmplitude;
uniform vec3 waveColor;
uniform vec3 backgroundColor;

vec4 mod289(vec4 x) { return x - floor(x * (1.0/289.0)) * 289.0; }
vec4 permute(vec4 x) { return mod289(((x * 34.0) + 1.0) * x); }
vec4 taylorInvSqrt(vec4 r) { return 1.79284291400159 - 0.85373472095314 * r; }
vec2 fade(vec2 t) { return t*t*t*(t*(t*6.0-15.0)+10.0); }

float cnoise(vec2 P) {
  vec4 Pi = floor(P.xyxy) + vec4(0.0,0.0,1.0,1.0);
  vec4 Pf = fract(P.xyxy) - vec4(0.0,0.0,1.0,1.0);
  Pi = mod289(Pi);
  vec4 ix = Pi.xzxz;
  vec4 iy = Pi.yyww;
  vec4 fx = Pf.xzxz;
  vec4 fy = Pf.yyww;
  vec4 i = permute(permute(ix) + iy);
  vec4 gx = fract(i * (1.0/41.0)) * 2.0 - 1.0;
  vec4 gy = abs(gx) - 0.5;
  vec4 tx = floor(gx + 0.5);
  gx = gx - tx;
  vec2 g00 = vec2(gx.x, gy.x);
  vec2 g10 = vec2(gx.y, gy.y);
  vec2 g01 = vec2(gx.z, gy.z);
  vec2 g11 = vec2(gx.w, gy.w);
  vec4 norm = taylorInvSqrt(vec4(dot(g00,g00), dot(g01,g01), dot(g10,g10), dot(g11,g11)));
  g00 *= norm.x; g01 *= norm.y; g10 *= norm.z; g11 *= norm.w;
  float n00 = dot(g00, vec2(fx.x, fy.x));
  float n10 = dot(g10, vec2(fx.y, fy.y));
  float n01 = dot(g01, vec2(fx.z, fy.z));
  float n11 = dot(g11, vec2(fx.w, fy.w));
  vec2 fade_xy = fade(Pf.xy);
  vec2 n_x = mix(vec2(n00, n01), vec2(n10, n11), fade_xy.x);
  return 2.3 * mix(n_x.x, n_x.y, fade_xy.y);
}

const int OCTAVES = 4;
float fbm(vec2 p) {
  float value = 0.0;
  float amp = 1.0;
  float freq = waveFrequency;
  for (int i = 0; i < OCTAVES; i++) {
    value += amp * abs(cnoise(p));
    p *= freq;
    amp *= waveAmplitude;
  }
  return value;
}

float pattern(vec2 p) {
  vec2 p2 = p - time * waveSpeed;
  return fbm(p + fbm(p2)); 
}


uniform float colorNum;
uniform float pixelSize;
const float bayerMatrix8x8[64] = float[64](
  0.0/64.0, 48.0/64.0, 12.0/64.0, 60.0/64.0,  3.0/64.0, 51.0/64.0, 15.0/64.0, 63.0/64.0,
  32.0/64.0,16.0/64.0, 44.0/64.0, 28.0/64.0, 35.0/64.0,19.0/64.0, 47.0/64.0, 31.0/64.0,
  8.0/64.0, 56.0/64.0,  4.0/64.0, 52.0/64.0, 11.0/64.0,59.0/64.0,  7.0/64.0, 55.0/64.0,
  40.0/64.0,24.0/64.0, 36.0/64.0, 20.0/64.0, 43.0/64.0,27.0/64.0, 39.0/64.0, 23.0/64.0,
  2.0/64.0, 50.0/64.0, 14.0/64.0, 62.0/64.0,  1.0/64.0,49.0/64.0, 13.0/64.0, 61.0/64.0,
  34.0/64.0,18.0/64.0, 46.0/64.0, 30.0/64.0, 33.0/64.0,17.0/64.0, 45.0/64.0, 29.0/64.0,
  10.0/64.0,58.0/64.0,  6.0/64.0, 54.0/64.0,  9.0/64.0,57.0/64.0,  5.0/64.0, 53.0/64.0,
  42.0/64.0,26.0/64.0, 38.0/64.0, 22.0/64.0, 41.0/64.0,25.0/64.0, 37.0/64.0, 21.0/64.0
);

vec3 dither(vec2 uv, vec3 color) {
  vec2 scaledCoord = floor(uv * resolution / pixelSize);
  int x = int(mod(scaledCoord.x, 8.0));
  int y = int(mod(scaledCoord.y, 8.0));
  float threshold = bayerMatrix8x8[y * 8 + x] - 0.25;
  float step = 1.0 / (colorNum - 1.0);
  color += threshold * step;
  float luminance = dot(color, vec3(0.2126, 0.7152, 0.0722));
  float bias = mix(0.2, 0.0, smoothstep(0.45, 0.8, luminance));
  color = clamp(color - bias, 0.0, 1.0);
  return floor(color * (colorNum - 1.0) + 0.5) / (colorNum - 1.0);
}

void main() {
  vec2 pixel = floor(gl_FragCoord.xy / pixelSize) * pixelSize;
  vec2 uv = pixel / resolution - 0.5;
  uv.x *= resolution.x / resolution.y;
  float f = pattern(uv);
  vec3 color = mix(backgroundColor, waveColor, clamp(f, 0.0, 1.0));
  fragmentColor = vec4(dither(gl_FragCoord.xy / resolution, color), 1.0);
}`;
const vertexSource = `#version 300 es
in vec2 position;
void main() { gl_Position = vec4(position, 0.0, 1.0); }`;

export function startLoginBackground(canvas) {
    const gl = canvas.getContext('webgl2', { alpha: false, antialias: false, depth: false, powerPreference: 'low-power' });
    if (!gl) return;
    const shaders = [];
    let program;
    let buffer;
    let frame;
    let disposed = false;
    const stop = () => { cancelAnimationFrame(frame); frame = undefined; };
    const compile = (type, source) => {
        const shader = gl.createShader(type);
        shaders.push(shader);
        gl.shaderSource(shader, source);
        gl.compileShader(shader);
        if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) throw new Error('Background shader unavailable');
        return shader;
    };
    try {
        program = gl.createProgram();
        gl.attachShader(program, compile(gl.VERTEX_SHADER, vertexSource));
        gl.attachShader(program, compile(gl.FRAGMENT_SHADER, fragmentSource));
        gl.linkProgram(program);
        if (!gl.getProgramParameter(program, gl.LINK_STATUS)) throw new Error('Background renderer unavailable');
        gl.useProgram(program);
        buffer = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
        const position = gl.getAttribLocation(program, 'position');
        gl.enableVertexAttribArray(position);
        gl.vertexAttribPointer(position, 2, gl.FLOAT, false, 0, 0);
    } catch {
        shaders.forEach(shader => gl.deleteShader(shader));
        if (program) gl.deleteProgram(program);
        if (buffer) gl.deleteBuffer(buffer);
        return;
    }
    const uniform = name => gl.getUniformLocation(program, name);
    const resolution = uniform('resolution');
    const time = uniform('time');
    gl.uniform1f(uniform('waveSpeed'), 0.05);
    gl.uniform1f(uniform('waveFrequency'), 3);
    gl.uniform1f(uniform('waveAmplitude'), 0.3);
    gl.uniform3f(uniform('waveColor'), 0.59, 0.63, 0.84);
    gl.uniform3f(uniform('backgroundColor'), 0.93, 0.92, 0.99);
    gl.uniform1f(uniform('colorNum'), 16);
    gl.uniform1f(uniform('pixelSize'), 2);
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    let elapsed = 0;
    let lastTick = 0;
    let lastDraw = 0;
    const draw = () => {
        gl.uniform1f(time, elapsed);
        gl.drawArrays(gl.TRIANGLES, 0, 3);
    };
    const tick = now => {
        if (disposed || document.hidden || reducedMotion.matches) { stop(); return; }
        if (lastTick) elapsed += Math.min((now - lastTick) / 1000, 0.1);
        lastTick = now;
        if (now - lastDraw >= 1000 / 24) { draw(); lastDraw = now; }
        frame = requestAnimationFrame(tick);
    };
    const resume = () => {
        stop();
        lastTick = 0;
        if (disposed || document.hidden) return;
        draw();
        if (!reducedMotion.matches) frame = requestAnimationFrame(tick);
    };
    const resize = () => {
        // Limit rendering cost on high-density displays; CSS keeps the canvas full size.
        const scale = Math.min(1, 1100 / innerWidth, 800 / innerHeight);
        canvas.width = Math.max(1, Math.round(innerWidth * scale));
        canvas.height = Math.max(1, Math.round(innerHeight * scale));
        gl.viewport(0, 0, canvas.width, canvas.height);
        gl.uniform2f(resolution, canvas.width, canvas.height);
        draw();
    };
    const contextLost = event => { event.preventDefault(); dispose(); canvas.hidden = true; };
    const dispose = () => {
        if (disposed) return;
        disposed = true;
        stop();
        window.removeEventListener('resize', resize);
        document.removeEventListener('visibilitychange', resume);
        reducedMotion.removeEventListener('change', resume);
        window.removeEventListener('pagehide', dispose);
        canvas.removeEventListener('webglcontextlost', contextLost);
        shaders.forEach(shader => gl.deleteShader(shader));
        gl.deleteBuffer(buffer);
        gl.deleteProgram(program);
    };
    window.addEventListener('resize', resize, { passive: true });
    document.addEventListener('visibilitychange', resume);
    reducedMotion.addEventListener('change', resume);
    window.addEventListener('pagehide', dispose, { once: true });
    canvas.addEventListener('webglcontextlost', contextLost);
    resize();
    canvas.classList.add('is-ready');
    resume();
}
