#!/usr/bin/env python3
"""Disposable localhost-only end-to-end WordPress authentication checks."""
import json,urllib.request,urllib.error,urllib.parse,base64,os
from pathlib import Path
base=os.environ.get('QUIXDEVS_HTTP_BASE', 'http://127.0.0.1:18867/wp-json/quixdevs-tickera/v1')
if urllib.parse.urlparse(base).hostname not in ('127.0.0.1','localhost'):
 raise RuntimeError('HTTP smoke testing is restricted to an isolated localhost site.')
creds=json.loads(Path(os.environ['QUIXDEVS_HTTP_CREDENTIALS']).read_text())
headers={'Authorization':'Basic '+base64.b64encode((creds['username']+':'+creds['application_password']).encode()).decode()}
checks=[]
def request(method,path,body=None,h=None):
 r=urllib.request.Request(base+path,data=None if body is None else json.dumps(body).encode(),headers={**(h or {}),**({'Content-Type':'application/json'} if body is not None else {})},method=method)
 try:
  with urllib.request.urlopen(r) as resp:return resp.status,json.loads(resp.read()),dict(resp.headers)
 except urllib.error.HTTPError as err:return err.code,json.loads(err.read()),dict(err.headers)
def check(label,actual,expected):
 assert actual==expected,(label,actual,expected)
 checks.append(label)
s,d,h=request('GET','/events');check('Anonymous HTTP rejected',s,401)
s,d,h=request('POST','/events',{'title':'HTTP QuixDevs event','start_at':'2027-01-14T09:00','end_at':'2027-01-14T18:00'},headers);check('Application Password event creation',s,201)
event=d['id'];check('Private no-store caching','no-store' in h.get('Cache-Control','') and 'private' in h.get('Cache-Control',''),True)
check('Forced author identity',d['author_id'],creds['user_id'])
s,d,h=request('GET',f'/events/{event}',h=headers);check('HTTP owner detail',s,200)
s,d,h=request('PATCH',f'/events/{event}',{'location':'Dhaka HTTP'},headers);check('HTTP event PATCH',s,200)
s,d,h=request('POST',f'/events/{event}/tickets',{'title':'HTTP VIP','price':'50.00','quantity':100},headers);check('HTTP ticket creation',s,201)
ticket=d['id']
s,d,h=request('GET',f'/events/{event}/tickets',h=headers);check('HTTP nested ticket list',s,200)
s,d,h=request('GET',f'/tickets/{ticket}',h=headers);check('HTTP ticket detail',s,200)
s,d,h=request('PATCH',f'/tickets/{ticket}',{'price':'60.00'},headers);check('HTTP ticket PATCH',s,200)
s,d,h=request('GET','/events',h=headers);check('HTTP owned events list',s,200)
cookie=json.loads(Path(os.environ['QUIXDEVS_HTTP_COOKIE']).read_text())
h={'Cookie':cookie['name']+'='+cookie['value']}
s,d,_=request('GET','/events',h=h);check('Cookie without nonce rejected',s,401)
h['X-WP-Nonce']=cookie['nonce'];s,d,_=request('GET','/events',h=h);check('Cookie with valid REST nonce',s,200)
h['X-WP-Nonce']='invalid';s,d,_=request('GET','/events',h=h);check('Invalid REST nonce rejected',s,403)
Path(os.environ.get('QUIXDEVS_HTTP_RESULTS', '/tmp/quixdevs-http-results.json')).write_text(json.dumps({'checks':checks,'event_id':event,'ticket_id':ticket},indent=2))
print(json.dumps({'passed':len(checks),'checks':checks},indent=2))
