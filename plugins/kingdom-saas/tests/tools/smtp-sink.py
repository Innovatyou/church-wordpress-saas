import socketserver, sys, base64, json, os
LOG = sys.argv[2]
class H(socketserver.StreamRequestHandler):
    def w(self, s): self.wfile.write((s + "\r\n").encode())
    def handle(self):
        rec = {'auth': None, 'mail_from': None, 'rcpt': [], 'data': ''}
        self.w('220 sink ESMTP')
        while True:
            line = self.rfile.readline().decode(errors='replace')
            if not line: return
            cmd = line.strip(); up = cmd.upper()
            if up.startswith('EHLO') or up.startswith('HELO'):
                self.wfile.write(b'250-sink\r\n250-AUTH PLAIN LOGIN\r\n250 OK\r\n')
            elif up.startswith('AUTH PLAIN'):
                parts = cmd.split()
                raw = parts[2] if len(parts) > 2 else None
                if not raw: self.w('334 '); raw = self.rfile.readline().decode().strip()
                rec['auth'] = base64.b64decode(raw).decode(errors='replace').split('\x00')[1]; self.w('235 ok')
            elif up.startswith('AUTH LOGIN'):
                self.w('334 VXNlcm5hbWU6'); u = base64.b64decode(self.rfile.readline().strip()).decode()
                self.w('334 UGFzc3dvcmQ6'); self.rfile.readline(); rec['auth'] = u; self.w('235 ok')
            elif up.startswith('MAIL FROM'): rec['mail_from'] = cmd[10:]; self.w('250 ok')
            elif up.startswith('RCPT TO'): rec['rcpt'].append(cmd[8:]); self.w('250 ok')
            elif up == 'DATA':
                self.w('354 go'); buf = []
                while True:
                    l = self.rfile.readline().decode(errors='replace')
                    if l in ('.\r\n', '.\n', ''): break
                    buf.append(l)
                rec['data'] = ''.join(buf); self.w('250 queued')
                with open(LOG, 'a') as f: f.write(json.dumps(rec) + "\n")
            elif up == 'QUIT': self.w('221 bye'); return
            elif up == 'RSET': self.w('250 ok')
            else: self.w('250 ok')
class T(socketserver.ThreadingTCPServer): allow_reuse_address = True
T(('127.0.0.1', int(sys.argv[1])), H).serve_forever()
