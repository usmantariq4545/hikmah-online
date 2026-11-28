#!/usr/bin/env python3
"""Short git/gh helpers for this repo, with short output (fewer tokens for Claude sessions, less typing for people).

    python scripts/dev/hk.py branch <name>        new branch from a fresh origin/main
    python scripts/dev/hk.py pr "<title>" [body]   push the branch, open a PR (body: text or @file), print its link
    python scripts/dev/hk.py checks [pr]           wait for CI; one line per result, plus the error lines of failures
    python scripts/dev/hk.py status                open PRs, live commit, whether a deploy is running
    python scripts/dev/hk.py merged                after a merge: switch to main and pull
    python scripts/dev/hk.py deploy                deploy origin/main (refuses if another deploy is running)

Needs git, the GitHub CLI (gh) and, for status/deploy, SSH to the server. Runs from anywhere inside the repo.
"""
import os
import re
import shutil
import subprocess
import sys
from pathlib import Path

SERVER = 'hikmah@157.173.114.25'
ROOT = Path(__file__).resolve().parents[2]
GH = shutil.which('gh') or r'C:\Program Files\GitHub CLI\gh.exe'
BUSY = r'ps -eo etime,cmd | grep "[D]EPLOY-OK\|[u]pgrade.sh\|[b]ackup.sh" || true'


def run(*cmd, check=True):
    """Runs a command in the repo root and returns its stdout (stripped)."""
    res = subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True, encoding='utf-8', errors='replace')
    if check and res.returncode:
        sys.exit(f'failed: {" ".join(cmd)}\n{(res.stderr or res.stdout).strip()[-1500:]}')
    return res.stdout.strip() if check else (res.returncode, res.stdout.strip())


def gh(*args, check=True):
    return run(GH, *args, check=check)


def branch(name):
    run('git', 'fetch', '-q', 'origin')
    if run('git', 'status', '--porcelain'):
        sys.exit('working tree has changes: commit or stash them first')
    run('git', 'switch', '-q', '-c', name, 'origin/main')
    print(f'on {name} (from origin/main {run("git", "rev-parse", "--short", "origin/main")})')


def pr(title, body=''):
    name = run('git', 'branch', '--show-current')
    if name == 'main':
        sys.exit('on main: make a branch first (hk.py branch <name>)')
    if body.startswith('@'):
        body = Path(body[1:]).read_text(encoding='utf-8')
    ahead = run('git', 'log', '--oneline', 'origin/main..HEAD')
    if not ahead:
        sys.exit('no commits on this branch yet')
    run('git', 'push', '-q', '-u', 'origin', name)
    existing = gh('pr', 'view', '--json', 'url', '-q', '.url', check=False)
    if existing[0] == 0 and existing[1]:
        print(f'pushed; PR already open: {existing[1]}')
        return
    out = gh('pr', 'create', '--base', 'main', '--title', title, '--body', body or title)
    print(re.search(r'https://github\.com/\S+', out).group(0))


def checks(number=''):
    args = ['pr', 'checks'] + ([number] if number else [])
    gh(*args, '--watch', '--interval', '20', check=False)
    code, out = gh(*args, check=False)
    counts, failed = {}, []
    for line in out.splitlines():
        parts = line.split('\t')
        if len(parts) >= 2:
            counts[parts[1]] = counts.get(parts[1], 0) + 1
            if parts[1] == 'fail':
                failed.append(parts)
    print(', '.join(f'{v} {k}' for k, v in sorted(counts.items())) or out[-500:])
    for parts in failed:
        job = re.search(r'/job/(\d+)', '\t'.join(parts))
        print(f'FAIL {parts[0]}')
        if job:
            _, log = gh('run', 'view', '--job', job.group(1), '--log-failed', check=False)
            keep = [l.split('Z ', 1)[-1] for l in log.splitlines()
                    if re.search(r'FILE:|\| (ERROR|WARNING)|Failed asserting|Error:|Exception', l)]
            print('\n'.join('  ' + l for l in keep[:15]) or '  (no error lines found; open the job log)')
    sys.exit(1 if failed else 0)


def status():
    _, prs = gh('pr', 'list', '--state', 'open', '--json', 'number,title,mergeable',
                '-q', '.[] | "#\\(.number) \\(.mergeable) \\(.title)"', check=False)
    print('open PRs:\n' + ('\n'.join('  ' + l for l in prs.splitlines()) or '  none'))
    code, out = run('ssh', SERVER, f'cat hikmah-online/DEPLOYED_COMMIT; {BUSY}', check=False)
    lines = out.splitlines()
    live = lines[0] if lines else '?'
    busy = [l for l in lines[1:] if 'unattended-upgrade' not in l]
    main = run('git', 'rev-parse', '--short', 'origin/main')
    print(f'live: {live}   origin/main: {main}' + ('   (live is behind main)' if not main.startswith(live) else ''))
    print('deploy running: ' + ('YES' if busy else 'no'))


def merged():
    run('git', 'fetch', '-q', 'origin')
    run('git', 'switch', '-q', 'main')
    run('git', 'pull', '-q', '--ff-only')
    print('main at ' + run('git', 'log', '--oneline', '-1'))


def deploy():
    _, out = run('ssh', SERVER, BUSY, check=False)
    if [l for l in out.splitlines() if 'unattended-upgrade' not in l]:
        sys.exit('another deploy is running on the server; wait for it to finish')
    merged()
    log = ROOT / 'backups' / 'deploy-last.log'
    log.parent.mkdir(exist_ok=True)
    with open(log, 'w', encoding='utf-8') as fh:
        code = subprocess.run(['bash', 'scripts/deploy-live.sh'], cwd=ROOT, stdout=fh, stderr=subprocess.STDOUT).returncode
    tail = log.read_text(encoding='utf-8', errors='replace').splitlines()[-6:]
    print('\n'.join(tail))
    print(f'full log: {log}')
    sys.exit(code)


COMMANDS = {'branch': branch, 'pr': pr, 'checks': checks, 'status': status, 'merged': merged, 'deploy': deploy}

if __name__ == '__main__':
    if len(sys.argv) < 2 or sys.argv[1] not in COMMANDS:
        sys.exit(__doc__)
    os.environ.setdefault('GH_PROMPT_DISABLED', '1')
    COMMANDS[sys.argv[1]](*sys.argv[2:])
