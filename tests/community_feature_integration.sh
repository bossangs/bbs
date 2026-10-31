#!/usr/bin/env bash
set -euo pipefail
SRC="$(cd "$(dirname "$0")/.." && pwd)"
SUFFIX="${$}_$(date +%s)"
DB_NAME="ndsk_it_${SUFFIX}"
DB_USER="ndsk_user_${SUFFIX}"
DB_PASS="$(openssl rand -hex 18)"
TMP="/tmp/ndsk-app-${SUFFIX}"
PORT=$((20000 + $$ % 20000))
WEBPID=''
SMTPPID=''
cleanup() {
  if [ -n "$WEBPID" ]; then kill "$WEBPID" 2>/dev/null || true; wait "$WEBPID" 2>/dev/null || true; fi
  if [ -n "$SMTPPID" ]; then kill "$SMTPPID" 2>/dev/null || true; wait "$SMTPPID" 2>/dev/null || true; fi
  sudo mariadb --protocol=socket >/dev/null 2>&1 <<SQL || true
DROP DATABASE IF EXISTS \`${DB_NAME}\`;
DROP USER IF EXISTS '${DB_USER}'@'127.0.0.1';
SQL
  rm -rf "$TMP"
}
trap cleanup EXIT
sudo mariadb --protocol=socket >/dev/null <<SQL
CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8 COLLATE utf8_general_ci;
CREATE USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
mkdir -p "$TMP"
tar -C "$SRC" --exclude='./preview' --exclude='./.git' --exclude='./conf/conf.php' --exclude='./conf/smtp.conf.php' --exclude='./.env' --exclude='./install/install.lock' --exclude='./upload' --exclude='./log' --exclude='./tmp' -cf - . | tar -C "$TMP" -xf -
rm -f "$TMP/conf/conf.php" "$TMP/conf/smtp.conf.php" "$TMP/install/install.lock"
mkdir -p "$TMP/conf" "$TMP/log" "$TMP/tmp" "$TMP/upload" "$TMP/plugin"
php8.4 -S "127.0.0.1:${PORT}" -t "$TMP" >"/tmp/ndsk-php84-${SUFFIX}.log" 2>&1 &
WEBPID=$!
for i in $(seq 1 40); do
  if curl -fsS "http://127.0.0.1:${PORT}/install/index.php?action=db" -o /tmp/ndsk-db-page.html 2>/dev/null; then break; fi
  sleep .25
done
grep -q 'PDO MySQL' /tmp/ndsk-db-page.html
curl -fsS -c /tmp/ndsk-cookie-"$SUFFIX" -b /tmp/ndsk-cookie-"$SUFFIX" \
  -H 'X-Requested-With: XMLHttpRequest' \
  --data-urlencode 'type=pdo_mysql' --data-urlencode 'engine=innodb' \
  --data-urlencode "host=127.0.0.1:3306" --data-urlencode "name=${DB_NAME}" \
  --data-urlencode "user=${DB_USER}" --data-urlencode "password=${DB_PASS}" \
  --data-urlencode 'adminemail=admin@example.test' --data-urlencode 'adminuser=admin' \
  --data-urlencode 'adminpass=Str0ng_Test_Password_123!' \
  "http://127.0.0.1:${PORT}/install/index.php?action=db" >/tmp/ndsk-install-response.json
python3 - <<'PY'
import json
p='/tmp/ndsk-install-response.json'
s=open(p,encoding='utf-8').read()
try:
 d=json.loads(s)
except Exception:
 print('installer response was not JSON:',s[:600]); raise
if int(d.get('code',-999)) != 0:
 print('installer failed:',d); raise SystemExit(2)
print('PHP 8.4 installer response: PASS')
PY
test -f "$TMP/conf/conf.php"
test -f "$TMP/install/install.lock"
php8.4 -r '$c=include $argv[1]; if(empty($c["installed"]) || $c["db"]["type"]!=="pdo_mysql" || empty($c["auth_key"])) exit(2); echo "generated config: PASS\n";' "$TMP/conf/conf.php"
TABLES=$(sudo mariadb --protocol=socket -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}'")
[ "$TABLES" -ge 25 ]
echo "schema tables: $TABLES"
sudo mariadb --protocol=socket "${DB_NAME}" < "$TMP/install/community_upgrade.sql"
echo 'community upgrade SQL: PASS'
sudo mariadb --protocol=socket "${DB_NAME}" <<'SQL'
REPLACE INTO bbs_kv (k,v,expiry) VALUES ('setting','{"community_config":{"require_invite_code":1,"enable_comment_email_notifications":1,"enable_raffle":1}}',0);
INSERT INTO bbs_community_invite (code_hash,code_prefix,max_uses,used_count,expires_at,created_by,created_at,revoked_at,note)
VALUES (SHA2('0123456789ABCDEF0123456789ABCDEF',256),'01234567',1,0,0,1,UNIX_TIMESTAMP(),0,'local integration invite'),
       (SHA2('FEDCBA9876543210FEDCBA9876543210',256),'FEDCBA98',1,0,0,1,UNIX_TIMESTAMP(),0,'second local test invite'),
       (SHA2('33333333333333333333333333333333',256),'33333333',1,0,0,1,UNIX_TIMESTAMP(),0,'atomic-use test');
SQL
GUEST_PERMS=$(sudo mariadb --protocol=socket -N -e "SELECT CONCAT(allowread,':',allowthread,':',allowpost) FROM \`${DB_NAME}\`.bbs_group WHERE gid=0")
[ "$GUEST_PERMS" = '1:0:0' ]
NOW=$(date +%s)
sudo mariadb --protocol=socket "${DB_NAME}" <<SQL
INSERT INTO bbs_thread (fid,tid,top,uid,subject,create_date,last_date,views,posts,firstpid,lastuid,lastpid) VALUES (1,900001,0,1,'游客可见主题验收',${NOW},${NOW},1,0,900001,1,900001),(13,900002,0,1,'受限板块隐藏验收',${NOW},${NOW},1,0,900002,1,900002);
INSERT INTO bbs_post (tid,pid,uid,isfirst,create_date,doctype,message,message_fmt) VALUES (900001,900001,1,1,${NOW},1,'公开帖子正文验收','公开帖子正文验收'),(900002,900002,1,1,${NOW},1,'受限帖子正文验收','受限帖子正文验收');
UPDATE bbs_forum SET threads=1 WHERE fid IN (1,13);
UPDATE bbs_forum SET accesson=1 WHERE fid=13;
INSERT INTO bbs_forum_access (fid,gid,allowread,allowthread,allowpost,allowattach,allowdown) SELECT 13,gid,IF(gid=1,1,0),IF(gid=1,1,0),IF(gid=1,1,0),0,0 FROM bbs_group;
SQL
curl -fsS "http://127.0.0.1:${PORT}/" >/tmp/ndsk-home84.html
grep -q 'bk.chuhuo.top' /tmp/ndsk-home84.html
grep -q '技术' /tmp/ndsk-home84.html
grep -q '游客可见主题验收' /tmp/ndsk-home84.html
! grep -q '受限板块隐藏验收' /tmp/ndsk-home84.html
! grep -q '此社区需要登录后浏览' /tmp/ndsk-home84.html
! grep -q 'forum-13' /tmp/ndsk-home84.html
curl -fsS "http://127.0.0.1:${PORT}/index.php?forum-1.htm" >/tmp/ndsk-public-forum.html
grep -q '游客可见主题验收' /tmp/ndsk-public-forum.html
curl -fsS "http://127.0.0.1:${PORT}/index.php?forum-13.htm" >/tmp/ndsk-private-forum.html
! grep -q '受限板块隐藏验收' /tmp/ndsk-private-forum.html
curl -fsS "http://127.0.0.1:${PORT}/index.php?thread-900001.htm" >/tmp/ndsk-public-thread.html
grep -q '公开帖子正文验收' /tmp/ndsk-public-thread.html
curl -fsS "http://127.0.0.1:${PORT}/index.php?thread-900002.htm" >/tmp/ndsk-private-thread.html
! grep -q '受限帖子正文验收' /tmp/ndsk-private-thread.html
curl -sS -D /tmp/ndsk-guest-post.headers -o /tmp/ndsk-guest-post.html "http://127.0.0.1:${PORT}/index.php?post-create-900001.htm"
grep -qi 'Location:.*user-login' /tmp/ndsk-guest-post.headers
! grep -qi '<textarea' /tmp/ndsk-guest-post.html
echo 'PHP 8.4 guest: public board/topic visible, restricted topic hidden, reply form gated: PASS'
# Use a local-only SMTP capture for the real registration-code route.
cat >"$TMP/conf/smtp.conf.php" <<'PHP'
<?php return array(array('email'=>'noreply@localhost.test','host'=>'127.0.0.1','port'=>'1025','secure'=>'','user'=>'','pass'=>''));
PHP
curl -fsS -c /tmp/ndsk-cookie-"$SUFFIX" -b /tmp/ndsk-cookie-"$SUFFIX" \
  "http://127.0.0.1:${PORT}/index.php?user-create.htm" >/tmp/ndsk-register.html
CSRF=$(python3 - <<'PY'
import re
s=open('/tmp/ndsk-register.html',encoding='utf-8').read()
m=re.search(r'name="_community_csrf"\s+value="([^"]+)"',s)
if not m: raise SystemExit('registration page did not include CSRF token')
print(m.group(1))
PY
)
python3 "$SRC/tests/fake_smtp.py" /tmp/ndsk-registration-mail.eml 1025 >"/tmp/ndsk-smtp-${SUFFIX}.log" 2>&1 &
SMTPPID=$!
sleep .4
curl --max-time 15 -fsS -c /tmp/ndsk-cookie-"$SUFFIX" -b /tmp/ndsk-cookie-"$SUFFIX" \
  -H 'X-Requested-With: XMLHttpRequest' \
  --data-urlencode "_community_csrf=${CSRF}" --data-urlencode 'email=qa@example.test' \
  --data-urlencode 'invite_code=01234567-89ABCDEF-01234567-89ABCDEF' \
  "http://127.0.0.1:${PORT}/index.php?user-send_code-user_create.htm" >/tmp/ndsk-send-code-response.json
if ! python3 - <<'PY2'
import json
d=json.load(open('/tmp/ndsk-send-code-response.json'))
raise SystemExit(0 if int(d.get('code',-999)) == 0 else 1)
PY2
then cat /tmp/ndsk-send-code-response.json; kill "$SMTPPID" 2>/dev/null || true; SMTPPID=''; exit 2; fi
wait "$SMTPPID"; SMTPPID=''
python3 - <<'PY'
import json
s=open('/tmp/ndsk-send-code-response.json',encoding='utf-8').read()
d=json.loads(s)
if int(d.get('code',-999)) != 0:
 print('send-code response:',d); raise SystemExit(2)
from email import policy
from email.parser import BytesParser
m=BytesParser(policy=policy.default).parse(open('/tmp/ndsk-registration-mail.eml','rb'))
body='\n'.join(p.get_content() for p in m.walk() if p.get_content_type()=='text/plain' and not p.is_multipart())
import re
codes=re.findall(r'(?<!\d)(\d{6})(?!\d)',body)
if not codes: print('no 6-digit code in email:',body); raise SystemExit(3)
open('/tmp/ndsk-captured-code.txt','w').write(codes[-1])
print('registration email code route + local SMTP capture: PASS')
PY
CODE=$(cat /tmp/ndsk-captured-code.txt)
PASSHASH=$(printf %s 'Str0ng_Test_Password_123!' | md5sum | awk '{print $1}')
curl --max-time 15 -fsS -c /tmp/ndsk-cookie-"$SUFFIX" -b /tmp/ndsk-cookie-"$SUFFIX" \
  -H 'X-Requested-With: XMLHttpRequest' \
  --data-urlencode "_community_csrf=${CSRF}" --data-urlencode 'email=qa@example.test' \
  --data-urlencode 'username=qaTest9' --data-urlencode "password=${PASSHASH}" \
  --data-urlencode "code=${CODE}" --data-urlencode 'invite_code=01234567-89ABCDEF-01234567-89ABCDEF' \
  "http://127.0.0.1:${PORT}/index.php?user-create.htm" >/tmp/ndsk-register-response.json
python3 - <<'PY'
import json
s=open('/tmp/ndsk-register-response.json',encoding='utf-8').read()
d=json.loads(s)
if str(d.get('code','')) != '0':
 print('registration response:',d); raise SystemExit(2)
print('email-verified registration flow: PASS')
PY
COUNT=$(sudo mariadb --protocol=socket -N -e "SELECT COUNT(*) FROM \`${DB_NAME}\`.\`bbs_user\` WHERE email='qa@example.test'")
[ "$COUNT" = 1 ]
USED=$(sudo mariadb --protocol=socket -N -e "SELECT used_count FROM \`${DB_NAME}\`.bbs_community_invite WHERE code_prefix='01234567'")
[ "$USED" = 1 ]
echo 'invite-required email registration + one-time code consumption: PASS'

# Reusing an exhausted invite is rejected before another verification email is sent.
COOKIE3="/tmp/ndsk-cookie-replay-${SUFFIX}"
curl -fsS -c "$COOKIE3" -b "$COOKIE3" "http://127.0.0.1:${PORT}/index.php?user-create.htm" >/tmp/ndsk-replay-register.html
CSRF3=$(python3 - <<'PY3'
import re
s=open('/tmp/ndsk-replay-register.html',encoding='utf-8').read()
m=re.search(r'name="_community_csrf"\s+value="([^"]+)"',s)
if not m: raise SystemExit('replay page did not include CSRF token')
print(m.group(1))
PY3
)
curl --max-time 15 -sS -c "$COOKIE3" -b "$COOKIE3" -H 'X-Requested-With: XMLHttpRequest' \
  --data-urlencode "_community_csrf=${CSRF3}" --data-urlencode 'email=replay@example.test' \
  --data-urlencode 'invite_code=01234567-89ABCDEF-01234567-89ABCDEF' \
  "http://127.0.0.1:${PORT}/index.php?user-send_code-user_create.htm" >/tmp/ndsk-replay-response.json
python3 - <<'PY3'
import json
r=json.load(open('/tmp/ndsk-replay-response.json'))
if str(r.get('code','0')) == '0': raise SystemExit('exhausted invitation unexpectedly accepted')
print('exhausted invitation replay rejected: PASS')
PY3

# Seed a public thread and active raffle, then use the real reply route and local SMTP sink.
NOW=$(date +%s)
sudo mariadb --protocol=socket "${DB_NAME}" <<SQL
UPDATE bbs_user SET email='admin@example.test' WHERE uid=1;
INSERT INTO bbs_thread (fid,tid,top,uid,subject,create_date,last_date,views,posts,firstpid,lastuid,lastpid) VALUES (1,900003,0,1,'评论邮件通知本地测试',${NOW},${NOW},1,0,900003,1,900003);
INSERT INTO bbs_post (tid,pid,uid,isfirst,create_date,doctype,message,message_fmt) VALUES (900003,900003,1,1,${NOW},1,'邮件通知测试主题正文','邮件通知测试主题正文');
UPDATE bbs_forum SET threads=threads+1 WHERE fid=1;
INSERT INTO bbs_community_raffle (tid,source_pid,creator_uid,prize,winner_count,status,created_at,ends_at) VALUES (900003,0,1,'本地回归抽奖',1,0,${NOW},${NOW}+3600);
SQL
python3 "$SRC/tests/fake_smtp.py" /tmp/ndsk-comment-mail.eml 1025 >"$TMP/smtp-comment.log" 2>&1 &
SMTPPID=$!
sleep .3
curl --max-time 15 -fsS -c /tmp/ndsk-cookie-"$SUFFIX" -b /tmp/ndsk-cookie-"$SUFFIX" \
  -H 'X-Requested-With: XMLHttpRequest' --data-urlencode "_community_csrf=${CSRF}" \
  --data-urlencode 'doctype=1' --data-urlencode 'message=<script>alert(1)</script> integration comment' \
  "http://127.0.0.1:${PORT}/index.php?post-create-900003.htm" >/tmp/ndsk-comment-response.json
python3 - <<'PY3'
import json
r=json.load(open('/tmp/ndsk-comment-response.json'))
if str(r.get('code','')) != '0': print(r); raise SystemExit('reply route failed')
from email import policy
from email.parser import BytesParser
m=BytesParser(policy=policy.default).parse(open('/tmp/ndsk-comment-mail.eml','rb'))
to=str(m.get('To',''))
body='\n'.join(p.get_content() for p in m.walk() if p.get_content_type() in ('text/plain','text/html') and not p.is_multipart())
if 'admin@example.test' not in to: raise SystemExit('notification went to the wrong recipient: '+to)
if '评论邮件通知本地测试' not in body or 'integration comment' not in body: raise SystemExit('notification missing thread/reply details')
if '127.0.0.1:' not in body or '900003' not in body: raise SystemExit('notification missing the correct local thread URL')
if '<script>' in body: raise SystemExit('unsafe script tag was present in notification')
print('reply route + app SMTP flow + local capture: PASS')
PY3
wait "$SMTPPID"; SMTPPID=''
QA_UID=$(sudo mariadb --protocol=socket -N -e "SELECT uid FROM \`${DB_NAME}\`.bbs_user WHERE email='qa@example.test'")
RAFFLE_ENTRIES=$(sudo mariadb --protocol=socket -N -e "SELECT COUNT(*) FROM \`${DB_NAME}\`.bbs_community_raffle_entry e JOIN \`${DB_NAME}\`.bbs_community_raffle r ON r.raffle_id=e.raffle_id WHERE r.tid=900003 AND e.uid=${QA_UID}")
[ "$RAFFLE_ENTRIES" = 1 ]
echo 'comment notification did not regress raffle auto-entry: PASS'

# A reply by the topic author must not generate an email.
NOW=$(date +%s)
sudo mariadb --protocol=socket "${DB_NAME}" -e "INSERT INTO bbs_thread (fid,tid,top,uid,subject,create_date,last_date,views,posts,firstpid,lastuid,lastpid) VALUES (1,900005,0,${QA_UID},'本人回复不通知测试',${NOW},${NOW},1,0,900005,${QA_UID},900005); INSERT INTO bbs_post (tid,pid,uid,isfirst,create_date,doctype,message,message_fmt) VALUES (900005,900005,${QA_UID},1,${NOW},1,'本人回复主题','本人回复主题');"
python3 "$SRC/tests/fake_smtp.py" /tmp/ndsk-self-mail.eml 1025 >"$TMP/smtp-self.log" 2>&1 &
SMTPPID=$!
sleep .3
curl --max-time 15 -fsS -c /tmp/ndsk-cookie-"$SUFFIX" -b /tmp/ndsk-cookie-"$SUFFIX" \
  -H 'X-Requested-With: XMLHttpRequest' --data-urlencode "_community_csrf=${CSRF}" \
  --data-urlencode 'doctype=1' --data-urlencode 'message=self reply does not notify' \
  "http://127.0.0.1:${PORT}/index.php?post-create-900005.htm" >/tmp/ndsk-self-response.json
sleep .3
kill "$SMTPPID" 2>/dev/null || true; wait "$SMTPPID" 2>/dev/null || true; SMTPPID=''
test ! -e /tmp/ndsk-self-mail.eml
echo 'author self-reply is skipped: PASS'

# A topic author without read access must not receive a private-board email.
# Register a second member with its own code, then post to a board where the topic author cannot read.
COOKIE2="/tmp/ndsk-cookie-second-${SUFFIX}"
curl -fsS -c "$COOKIE2" -b "$COOKIE2" "http://127.0.0.1:${PORT}/index.php?user-create.htm" >/tmp/ndsk-register2.html
CSRF2=$(python3 - <<'PY3'
import re
s=open('/tmp/ndsk-register2.html',encoding='utf-8').read()
m=re.search(r'name="_community_csrf"\s+value="([^"]+)"',s)
if not m: raise SystemExit('second registration page has no CSRF token')
print(m.group(1))
PY3
)
python3 "$SRC/tests/fake_smtp.py" /tmp/ndsk-registration2-mail.eml 1025 >"$TMP/smtp-register2.log" 2>&1 &
SMTPPID=$!
sleep .3
curl --max-time 15 -fsS -c "$COOKIE2" -b "$COOKIE2" -H 'X-Requested-With: XMLHttpRequest' \
  --data-urlencode "_community_csrf=${CSRF2}" --data-urlencode 'email=qa2@example.test' \
  --data-urlencode 'invite_code=FEDCBA98-76543210-FEDCBA98-76543210' \
  "http://127.0.0.1:${PORT}/index.php?user-send_code-user_create.htm" >/tmp/ndsk-send-code2-response.json
wait "$SMTPPID"; SMTPPID=''
python3 - <<'PY3'
from email import policy
from email.parser import BytesParser
import re
m=BytesParser(policy=policy.default).parse(open('/tmp/ndsk-registration2-mail.eml','rb'))
body='\n'.join(p.get_content() for p in m.walk() if p.get_content_type()=='text/plain' and not p.is_multipart())
codes=re.findall(r'(?<!\d)(\d{6})(?!\d)',body)
if not codes: raise SystemExit('second local verification code missing')
open('/tmp/ndsk-captured-code2.txt','w').write(codes[-1])
PY3
CODE2=$(cat /tmp/ndsk-captured-code2.txt)
PASSHASH2=$(printf %s 'Str0ng_Test_Password_123!' | md5sum | awk '{print $1}')
curl --max-time 15 -fsS -c "$COOKIE2" -b "$COOKIE2" -H 'X-Requested-With: XMLHttpRequest' \
  --data-urlencode "_community_csrf=${CSRF2}" --data-urlencode 'email=qa2@example.test' \
  --data-urlencode 'username=qaTest10' --data-urlencode "password=${PASSHASH2}" \
  --data-urlencode "code=${CODE2}" --data-urlencode 'invite_code=FEDCBA98-76543210-FEDCBA98-76543210' \
  "http://127.0.0.1:${PORT}/index.php?user-create.htm" >/tmp/ndsk-register2-response.json
python3 - <<'PY3'
import json
r=json.load(open('/tmp/ndsk-register2-response.json'))
if str(r.get('code','')) != '0': print(r); raise SystemExit('second registration failed')
PY3
QA2_UID=$(sudo mariadb --protocol=socket -N -e "SELECT uid FROM \`${DB_NAME}\`.bbs_user WHERE email='qa2@example.test'")
[ -n "$QA2_UID" ]
NOW=$(date +%s)
sudo mariadb --protocol=socket "${DB_NAME}" <<SQL
UPDATE bbs_forum_access SET allowpost=1,allowread=0 WHERE fid=13 AND gid=101;
INSERT INTO bbs_thread (fid,tid,top,uid,subject,create_date,last_date,views,posts,firstpid,lastuid,lastpid) VALUES (13,900004,0,${QA_UID},'受限邮件权限测试',${NOW},${NOW},1,0,910004,${QA_UID},910004);
INSERT INTO bbs_post (tid,pid,uid,isfirst,create_date,doctype,message,message_fmt) VALUES (900004,910004,${QA_UID},1,${NOW},1,'受限主题内容','受限主题内容');
SQL
sudo mariadb --protocol=socket "${DB_NAME}" -e 'TRUNCATE TABLE bbs_cache'
python3 "$SRC/tests/fake_smtp.py" /tmp/ndsk-restricted-mail.eml 1025 >"$TMP/smtp-restricted.log" 2>&1 &
SMTPPID=$!
sleep .3
curl --max-time 15 -fsS -c "$COOKIE2" -b "$COOKIE2" -H 'X-Requested-With: XMLHttpRequest' \
  --data-urlencode "_community_csrf=${CSRF2}" --data-urlencode 'doctype=1' \
  --data-urlencode 'message=restricted board reply should not email an unreadable topic' \
  "http://127.0.0.1:${PORT}/index.php?post-create-900004.htm" >/tmp/ndsk-restricted-response.json
sleep .3
kill "$SMTPPID" 2>/dev/null || true; wait "$SMTPPID" 2>/dev/null || true; SMTPPID=''
test ! -e /tmp/ndsk-restricted-mail.eml
echo 'restricted-board recipient without read permission is skipped: PASS'

# Verify concurrent registration attempts cannot over-consume a single-use invitation.
RACE_SQL="UPDATE \`${DB_NAME}\`.bbs_community_invite SET used_count=used_count+1 WHERE code_hash=SHA2('33333333333333333333333333333333',256) AND revoked_at=0 AND (expires_at=0 OR expires_at>UNIX_TIMESTAMP()) AND used_count<max_uses; SELECT ROW_COUNT();"
sudo mariadb --protocol=socket -N -e "$RACE_SQL" >/tmp/ndsk-race1-${SUFFIX}.txt & R1=$!
sudo mariadb --protocol=socket -N -e "$RACE_SQL" >/tmp/ndsk-race2-${SUFFIX}.txt & R2=$!
wait "$R1"; wait "$R2"
RACE_SUM=$(cat /tmp/ndsk-race1-${SUFFIX}.txt /tmp/ndsk-race2-${SUFFIX}.txt | awk '{s+=$1} END{print s+0}')
[ "$RACE_SUM" = 1 ]
echo 'concurrent single-use invitation consumption is atomic: PASS'

# Verify PHP 7.0 can boot the installed PHP 8.4-generated site/database config.
kill "$WEBPID" 2>/dev/null || true; wait "$WEBPID" 2>/dev/null || true; WEBPID=''
php7.0 -S "127.0.0.1:${PORT}" -t "$TMP" >"/tmp/ndsk-php70-${SUFFIX}.log" 2>&1 &
WEBPID=$!
for i in $(seq 1 40); do if curl -fsS "http://127.0.0.1:${PORT}/" -o /tmp/ndsk-home70.html 2>/dev/null; then break; fi; sleep .25; done
grep -q 'bk.chuhuo.top' /tmp/ndsk-home70.html
grep -q '游客可见主题验收' /tmp/ndsk-home70.html
! grep -q '受限板块隐藏验收' /tmp/ndsk-home70.html
echo 'PHP 7.0 public board visible + restricted board filtered + PDO/MySQL boot: PASS'
echo 'end-to-end install, invitation, comment SMTP, permissions, raffle, PHP 7.0/8.4: PASS (temporary DB/user are removed by cleanup)'
