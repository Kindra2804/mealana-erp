"""
Demo-Installation auf dem Webspace nahtlos.indranet.at (World4You) einrichten bzw. aktualisieren.

    python tools/demo/webspace_aktualisieren.py              Code hochladen (Konfiguration + Daten bleiben)
    python tools/demo/webspace_aktualisieren.py --erzeugen   … und danach die Demo-Daten neu erzeugen

Aufbau am Webspace (PHP darf nur innerhalb von web/ lesen → open_basedir):
    web/nahtlos/              Startordner der Subdomain (später Teaser-Webseite)
    web/nahtlos/demo -> erp/public     https://nahtlos.indranet.at/demo/
    web/nahtlos/erp/          Code, config, storage — per .htaccess von außen gesperrt

Zugang: D:\\ERP\\mealana\\import\\nahtlos.indranet.at Zugänge.txt (nie im Repo). Braucht paramiko.
Ins Paket kommt NUR Code — keine echten Daten (Uploads, Logos, Datenbank-Abzüge, storage,
MeaLana-Hilfsskripte, Lizenzgenerator).
"""
import io, os, re, secrets, subprocess, sys, tarfile
import paramiko

REPO     = r'D:\ERP\mealana'
ZUGANG   = os.path.join(REPO, 'import', 'nahtlos.indranet.at Zugänge.txt')
BASIS    = 'web/nahtlos'           # relativ zum Home des SSH-Benutzers
PHP_CLI  = '/opt/alt/php85/usr/bin/php'   # gleiche Version wie der Webserver

AUSSCHLIESSEN = [
    r'^erp/storage/', r'^erp/public/uploads/', r'^erp/public/img/logos/', r'^erp/public/img/hersteller/',
    r'^erp/public/shell-(test|demo)\.php$', r'^erp/src/modules/lizenzgenerator/',
    r'^erp/config/(?!bootstrap\.php$|lizenz_public\.pem$|\.gitkeep$)',
    r'^erp/database/(?!baseline_schema\.sql$|migrate\.php$|create_admin\.php$|migrations/)',
    r'^erp/scripts/(?!demo/)',
    r'^erp/public/img/logo\.png$',   # altes MeaLana-Logo, wird nirgends mehr verwendet
]


def zugang():
    t = open(ZUGANG, encoding='utf-8').read()
    db, ssh = t.split('SSH:')
    g = lambda muster, text: re.search(muster, text).group(1)
    return ({'host': g(r'Server\s+(\S+)', db), 'name': g(r'Datenbank\s+(\S+)', db),
             'user': g(r'Benutzer\s+(\S+)', db), 'pw': g(r'Passwort\s+(\S+)', db)},
            {'host': g(r'Server:\s*(\S+)', ssh), 'user': g(r'Benutzername:\s*(\S+)', ssh), 'pw': g(r'Passwort:\s*(\S+)', ssh)})


def paket() -> bytes:
    dateien = subprocess.run(['git', 'ls-files', '--cached', '--others', '--exclude-standard', 'erp'],
                             cwd=REPO, capture_output=True, text=True, check=True).stdout.splitlines()
    dateien = [d for d in dateien if not any(re.search(m, d) for m in AUSSCHLIESSEN) and os.path.isfile(os.path.join(REPO, d))]
    for wurzel, _, namen in os.walk(os.path.join(REPO, 'erp', 'vendor')):
        dateien += [os.path.relpath(os.path.join(wurzel, n), REPO).replace('\\', '/') for n in namen]
    puffer = io.BytesIO()
    with tarfile.open(fileobj=puffer, mode='w:gz') as tar:
        for d in sorted(set(dateien)):
            tar.add(os.path.join(REPO, d), arcname=d)
    print(f'Paket: {len(set(dateien))} Dateien, {len(puffer.getvalue()) / 1e6:.1f} MB')
    return puffer.getvalue()


def main():
    db, ssh = zugang()
    c = paramiko.SSHClient(); c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(ssh['host'], 22, ssh['user'], ssh['pw'], timeout=20, look_for_keys=False, allow_agent=False)

    def run(befehl, zeigen=True):
        _, o, e = c.exec_command(befehl, timeout=900)
        aus, fehler = o.read().decode('utf-8', 'replace'), e.read().decode('utf-8', 'replace')
        if zeigen and aus.strip(): print(aus.rstrip())
        if fehler.strip(): print('! ' + fehler.rstrip())
        return aus

    sftp = c.open_sftp()
    sftp.putfo(io.BytesIO(paket()), 'tmp/nahtlos_paket.tar.gz')

    # Neu auspacken, dann Konfiguration + Daten der bisherigen Installation übernehmen
    run(f"""set -e; cd {BASIS}
rm -rf _neu && mkdir _neu && tar -xzf ~/tmp/nahtlos_paket.tar.gz -C _neu && rm ~/tmp/nahtlos_paket.tar.gz
if [ -d erp ]; then
  for f in config/database.php config/encryption.php config/demo.php; do [ -f erp/$f ] && cp -p erp/$f _neu/erp/$f; done
  for d in storage public/uploads public/img/logos; do [ -d erp/$d ] && rm -rf _neu/erp/$d && mv erp/$d _neu/erp/$d; done
  rm -rf _alt && mv erp _alt
fi
mv _neu/erp erp && rm -rf _neu _alt
mkdir -p erp/storage/dokumente erp/storage/bons erp/storage/picklisten erp/public/uploads/artikel erp/public/img/logos
printf 'Require all denied\\n' > erp/.htaccess
[ -L demo ] || ln -s erp/public demo
printf 'User-agent: *\\nDisallow: /demo/\\n' > robots.txt
echo "Code aktualisiert: $(cat erp/VERSION)" """)

    # Erste Einrichtung: Konfiguration nur anlegen, wenn sie fehlt
    def anlegen(pfad, inhalt):
        try:
            sftp.stat(pfad); return False
        except FileNotFoundError:
            with sftp.open(pfad, 'w') as f: f.write(inhalt)
            sftp.chmod(pfad, 0o600); print('angelegt:', pfad); return True

    cfg = f'{BASIS}/erp/config'
    anlegen(f'{cfg}/database.php', "<?php\nreturn [\n    'host' => '%s',\n    'dbname' => '%s',\n    'username' => '%s',\n    'password' => '%s',\n    'charset' => 'utf8mb4',\n];\n"
            % (db['host'], db['name'], db['user'], db['pw'].replace("'", "\\'")))
    anlegen(f'{cfg}/encryption.php', "<?php\nreturn [\n    'master_key' => '%s',\n    'search_key' => '%s',\n];\n" % (secrets.token_hex(32), secrets.token_hex(32)))
    if anlegen(f'{cfg}/demo.php', "<?php\n// Macht diese Installation zur DEMO (src/core/Demo.php)\nreturn [\n    'aktiv' => true,\n    'login' => 'demo',\n    'passwort' => 'demo',\n"
               "    'reset_schluessel' => '%s',\n    'php_cli' => '%s',\n];\n" % (secrets.token_urlsafe(24), PHP_CLI)):
        print('Cron-Schlüssel steht in erp/config/demo.php (reset_schluessel).')
    sftp.close()

    if '--erzeugen' in sys.argv:
        run(f'cd {BASIS}/erp && {PHP_CLI} scripts/demo/demo_erzeugen.php')
    c.close()


if __name__ == '__main__':
    main()
