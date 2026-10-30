import json, glob, subprocess, re, sys

def esc_sql(v):
    return v.replace('\\', '\\\\').replace("'", "\\'")

def set_inner(path):
    inner = open(path, encoding='utf-8').read().rstrip('\n')
    raw = open('pre-test-backup.json', encoding='utf-8').read().rstrip('\n')
    # 外科手术替换 ENavSets 值段
    key = '"ENavSets":"'
    i = raw.index(key) + len(key)
    j = i
    while True:
        if raw[j] == '\\': j += 2; continue
        if raw[j] == '"': break
        j += 1
    new_raw = raw[:i] + inner.replace('\\', '\\\\').replace('"', '\\"') + raw[j:]
    sql = "UPDATE typecho_options SET value='" + esc_sql(new_raw) + "' WHERE name='theme:ecod-theme';\n"
    open('seed.sql', 'w', encoding='utf-8').write(sql)
    subprocess.run(['mysql','-uroot','-pHs@12345','ecod','--default-character-set=utf8mb4'],
                   stdin=open('seed.sql'), capture_output=True)

def shot(name, force_open=True):
    css_files = [f for f in sorted(glob.glob('/Users/bws/blog/ecod/usr/themes/ecod-theme/assets/dist/css/ecod.*.min.css')) if 'critical' not in f]
    css = open(css_files[-1], encoding='utf-8').read()
    html = subprocess.run(['curl','-s','http://ecod.cc/'], capture_output=True, text=True).stdout
    m = re.search(r'<header class="ecod_header".*?</header>', html, re.S)
    assert m, 'header not found'
    header = m.group(0)
    probe = ('<!DOCTYPE html><html lang="zh-CN" data-theme="light"><head><meta charset="UTF-8">'
             '<style>' + css + '</style><style>body{background:#fff;margin:0}</style></head><body>'
             + header + '<div style="height:400px"></div>'
             '<script>document.querySelectorAll(".ecod_nav__links li.has-sub").forEach(function(l){l.classList.add("is-open")});</script>'
             '</body></html>')
    open('/Users/bws/blog/ecod/__navcheck.html', 'w', encoding='utf-8').write(probe)
    subprocess.run(['/Applications/Google Chrome.app/Contents/MacOS/Google Chrome','--headless','--no-sandbox',
                    '--disable-gpu','--user-data-dir=/tmp/ecod-navb-prof','--window-size=1440,420',
                    '--screenshot=' + name, '--virtual-time-budget=5000',
                    'http://ecod.cc/__navcheck.html'], capture_output=True)
    print('shot:', name)

mode = sys.argv[1]
if mode == 'a':
    set_inner('testA_inner.json'); shot('probe-group.png')
elif mode == 'b':
    set_inner('testB_inner.json'); shot('probe-rows.png')
elif mode == 'restore':
    set_inner_from_backup()
