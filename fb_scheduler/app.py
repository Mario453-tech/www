"""VPS scheduler: scheduled preparation and manual publication queue."""
import os
import sqlite3
from datetime import datetime, timezone
from zoneinfo import ZoneInfo
from contextlib import contextmanager
from html import escape
from urllib.parse import urlparse
from fastapi import FastAPI, Form, HTTPException, Depends
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.security import HTTPBasic, HTTPBasicCredentials
from apscheduler.schedulers.background import BackgroundScheduler
import secrets

TZ = ZoneInfo('Europe/Warsaw')
DB = os.getenv('DB_PATH', '/app/data/posts.db')
USER = os.getenv('ADMIN_USER', 'admin')
PASSWORD = os.getenv('ADMIN_PASSWORD')
if not PASSWORD:
    raise RuntimeError('Set ADMIN_PASSWORD environment variable')
os.makedirs(os.path.dirname(os.path.abspath(DB)), exist_ok=True)
security = HTTPBasic()
app = FastAPI(title='FB Scheduler')
scheduler = BackgroundScheduler(timezone=TZ)

@contextmanager
def db():
    connection = sqlite3.connect(DB)
    connection.row_factory = sqlite3.Row
    try:
        yield connection
        connection.commit()
    finally:
        connection.close()

def auth(credentials: HTTPBasicCredentials = Depends(security)):
    if not (secrets.compare_digest(credentials.username, USER) and secrets.compare_digest(credentials.password, PASSWORD)):
        raise HTTPException(status_code=401, headers={'WWW-Authenticate': 'Basic'}, detail='Unauthorized')

def valid_group_url(url):
    p = urlparse(url)
    return p.scheme == 'https' and p.hostname in ('facebook.com', 'www.facebook.com', 'm.facebook.com') and p.path.startswith('/groups/')

def process_due():
    now = datetime.now(timezone.utc).isoformat()
    with db() as c:
        c.execute("UPDATE posts SET status='ready' WHERE status='scheduled' AND scheduled_utc <= ?", (now,))

@app.on_event('startup')
def startup():
    with db() as c:
        c.execute('''CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            group_url TEXT NOT NULL, body TEXT NOT NULL,
            scheduled_utc TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'scheduled',
            created_utc TEXT NOT NULL, published_url TEXT DEFAULT ''
        )''')
    process_due()
    scheduler.add_job(process_due, 'interval', seconds=30, id='due', replace_existing=True, max_instances=1)
    scheduler.start()

@app.on_event('shutdown')
def shutdown():
    scheduler.shutdown(wait=False)

CSS = '''<style>body{font:16px system-ui;max-width:950px;margin:35px auto;padding:0 16px;color:#17212b;background:#f6f8fb}main{background:white;padding:25px;border-radius:14px}input,textarea{display:block;box-sizing:border-box;width:100%;padding:11px;margin:7px 0 15px;border:1px solid #bbb;border-radius:7px}button{padding:10px 16px;background:#1769e0;color:white;border:0;border-radius:7px;cursor:pointer}table{border-collapse:collapse;width:100%}td,th{padding:12px;border-bottom:1px solid #ddd;text-align:left;vertical-align:top;word-break:break-word}small{color:#666}form.inline{display:inline}form.inline button{background:#555}a{color:#1769e0}</style>'''

@app.get('/', response_class=HTMLResponse)
def index(_: str = Depends(auth)):
    process_due()
    with db() as c:
        posts = c.execute('SELECT * FROM posts ORDER BY scheduled_utc DESC').fetchall()
    rows = []
    for p in posts:
        date = datetime.fromisoformat(p['scheduled_utc']).astimezone(TZ).strftime('%Y-%m-%d %H:%M')
        action = ''
        if p['status'] == 'ready':
            action = f'<form class="inline" method="post" action="/posts/{p["id"]}/done"><input name="published_url" placeholder="Link do opublikowanego posta (opcjonalnie)"><button>Oznacz jako opublikowany</button></form>'
        if p['status'] in ('scheduled', 'ready'):
            action += f' <form class="inline" method="post" action="/posts/{p["id"]}/cancel"><button>Anuluj</button></form>'
        rows.append(f'<tr><td>{p["id"]}</td><td><a target="_blank" rel="noopener noreferrer" href="{escape(p["group_url"], quote=True)}">Otwórz grupę</a></td><td>{escape(p["body"])[:220]}</td><td>{date}</td><td>{escape(p["status"])}</td><td>{action}</td></tr>')
    return HTMLResponse('<html lang="pl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'+CSS+'<main><h1>FB Scheduler</h1><p>Planowanie publikacji w grupach. W wyznaczonym czasie post przechodzi do kolejki „ready” do ręcznej publikacji.</p><form method="post" action="/posts"><label>Adres grupy Facebook</label><input type="url" name="group_url" placeholder="https://www.facebook.com/groups/..." required><label>Treść posta</label><textarea name="body" rows="5" required maxlength="10000"></textarea><label>Data i godzina (Polska)</label><input type="datetime-local" name="scheduled_local" required><button>Zaplanuj post</button></form><h2>Kolejka</h2><div style="overflow-x:auto"><table><thead><tr><th>ID</th><th>Grupa</th><th>Treść</th><th>Termin</th><th>Status</th><th>Akcje</th></tr></thead><tbody>'+''.join(rows)+'</tbody></table></div><p><small>Nie przechowuje haseł ani ciasteczek Facebooka. Publikacja wymaga ręcznego zatwierdzenia na Facebooku.</small></p></main></html>')

@app.post('/posts')
def create(group_url: str = Form(...), body: str = Form(...), scheduled_local: str = Form(...), _: str = Depends(auth)):
    if not valid_group_url(group_url) or not body.strip() or len(body)>10000:
        raise HTTPException(400, 'Invalid group URL or body')
    try:
        local = datetime.fromisoformat(scheduled_local).replace(tzinfo=TZ)
    except ValueError:
        raise HTTPException(400, 'Invalid date')
    utc = local.astimezone(timezone.utc)
    if utc <= datetime.now(timezone.utc):
        raise HTTPException(400, 'Date must be in the future')
    with db() as c:
        c.execute('INSERT INTO posts(group_url,body,scheduled_utc,status,created_utc) VALUES(?,?,?,?,?)', (group_url,body.strip(),utc.isoformat(),'scheduled',datetime.now(timezone.utc).isoformat()))
    return RedirectResponse('/', status_code=303)

@app.post('/posts/{post_id}/done')
def done(post_id: int, published_url: str = Form(''), _: str = Depends(auth)):
    if published_url and urlparse(published_url).scheme not in ('https', 'http'):
        raise HTTPException(400, 'Invalid URL')
    with db() as c:
        c.execute("UPDATE posts SET status='published',published_url=? WHERE id=? AND status='ready'", (published_url,post_id))
    return RedirectResponse('/', status_code=303)

@app.post('/posts/{post_id}/cancel')
def cancel(post_id: int, _: str = Depends(auth)):
    with db() as c:
        c.execute("UPDATE posts SET status='cancelled' WHERE id=? AND status IN ('ready','scheduled')", (post_id,))
    return RedirectResponse('/', status_code=303)
