/* index.html → 워드프레스 플러그인(wordpress/ruda-landing) 생성
   사용법:  node build-wp.js   (index.html 을 고친 뒤 다시 실행하면 플러그인도 갱신됨) */
const fs = require('fs');
const path = require('path');

const ROOT = __dirname;
const OUT = path.join(ROOT, 'wordpress', 'ruda-landing');
const html = fs.readFileSync(path.join(ROOT, 'index.html'), 'utf8');

const between = (s, a, b, from = 0) => {
  const i = s.indexOf(a, from); if (i < 0) throw new Error('찾을 수 없음: ' + a);
  const j = s.indexOf(b, i + a.length); if (j < 0) throw new Error('찾을 수 없음: ' + b);
  return { text: s.slice(i + a.length, j), end: j + b.length };
};

// 1) CSS
const css = between(html, '<style>', '</style>').text.trim() + '\n';

// 2) 본문 마크업 (body 시작 ~ 첫 외부 스크립트 직전)
const bodyStart = html.indexOf('<body>') + '<body>'.length;
const scriptsStart = html.indexOf('<script src=', bodyStart);
let markup = html.slice(bodyStart, scriptsStart).trim();
if (markup.includes('<?')) throw new Error('마크업에 "<?" 가 있어 PHP 템플릿과 충돌합니다.');
markup = markup.replace(/src="(?:\.\/)?assets\//g, 'src="<?php echo esc_url( RUDA_LANDING_URL . \'assets/\' ); ?>');

// 3) 메인 스크립트 (외부 스크립트들 다음의 인라인 <script>)
const lastExternal = html.lastIndexOf('<script src=');
const inlineStart = html.indexOf('<script>', lastExternal);
const js = between(html, '<script>', '</script>', inlineStart).text.trim() + '\n';

// 4) 외부 리소스 (폰트 CSS, 라이브러리 JS)
const fonts = [...html.matchAll(/<link rel="stylesheet" href="([^"]+)"/g)].map(m => m[1]);
const libs = [...html.matchAll(/<script src="([^"]+)"><\/script>/g)].map(m => m[1]);
const title = (html.match(/<title>([^<]*)<\/title>/) || [, '루다애드'])[1];

// 출력
fs.rmSync(OUT, { recursive: true, force: true });
for (const d of ['templates', 'assets/css', 'assets/js', 'assets/img']) fs.mkdirSync(path.join(OUT, d), { recursive: true });
fs.writeFileSync(path.join(OUT, 'assets/css/landing.css'), css);
fs.writeFileSync(path.join(OUT, 'assets/js/landing.js'), js);
for (const f of fs.readdirSync(path.join(ROOT, 'assets/img'))) fs.copyFileSync(path.join(ROOT, 'assets/img', f), path.join(OUT, 'assets/img', f));

const tpl = fs.readFileSync(path.join(ROOT, 'wp-src', 'landing.php'), 'utf8')
  .replace('{{MARKUP}}', markup)
  .replace('{{TITLE}}', title.replace(/'/g, "\\'"));
fs.writeFileSync(path.join(OUT, 'templates/landing.php'), tpl);

const plugin = fs.readFileSync(path.join(ROOT, 'wp-src', 'ruda-landing.php'), 'utf8')
  .replace('{{FONTS}}', fonts.map(u => `'${u}'`).join(",\n\t\t"))
  .replace('{{LIBS}}', libs.map(u => `'${u}'`).join(",\n\t\t"));
fs.writeFileSync(path.join(OUT, 'ruda-landing.php'), plugin);

// 업로드용 zip (Windows 내장 tar 사용 — 경로 구분자가 '/' 라 리눅스 서버에서도 정상)
const zip = path.join(ROOT, 'wordpress', 'ruda-landing.zip');
fs.rmSync(zip, { force: true });
require('child_process').execFileSync('tar', ['-a', '-c', '-f', 'ruda-landing.zip', 'ruda-landing'], { cwd: path.join(ROOT, 'wordpress') });

console.log('플러그인 생성 완료:', path.relative(ROOT, OUT), '+', path.relative(ROOT, zip), (fs.statSync(zip).size / 1e6).toFixed(1) + 'MB');
console.log('  CSS', (css.length / 1024).toFixed(1) + 'KB, JS', (js.length / 1024).toFixed(1) + 'KB, 이미지', fs.readdirSync(path.join(OUT, 'assets/img')).length + '개');
console.log('  폰트', fonts.length + '개, 라이브러리', libs.length + '개');
