"""Local SMTP/TLS fixture only; no external delivery or real credentials."""
from pathlib import Path
from email import policy
from email.parser import BytesParser
import os, socketserver, ssl, subprocess, tempfile, threading
ROOT=Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='stridebr-smtp-') as tmp:
    cert=Path(tmp)/'cert.pem';key=Path(tmp)/'key.pem'
    subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(key),'-out',str(cert),'-days','1','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost'],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    tls=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);tls.load_cert_chain(cert,key)
    received=[]
    class SMTP(socketserver.BaseRequestHandler):
        def handle(self):
            sock=self.request;stream=sock.makefile('rb');sock.sendall(b'220 fixture ESMTP\r\n');data=False;message=[]
            while True:
                line=stream.readline()
                if not line:return
                if data:
                    if line==b'.\r\n':received.append(b''.join(message));data=False;sock.sendall(b'250 accepted\r\n')
                    else:message.append(line)
                elif line.startswith(b'EHLO'):sock.sendall(b'250-fixture\r\n250 STARTTLS\r\n')
                elif line.startswith(b'STARTTLS'):
                    sock.sendall(b'220 TLS\r\n');stream.close();sock=tls.wrap_socket(sock,server_side=True);stream=sock.makefile('rb')
                elif line.startswith((b'MAIL',b'RCPT',b'RSET')):sock.sendall(b'250 OK\r\n')
                elif line.startswith(b'DATA'):data=True;sock.sendall(b'354 message\r\n')
                elif line.startswith(b'QUIT'):sock.sendall(b'221 bye\r\n');return
                else:sock.sendall(b'500 unsupported\r\n')
    with socketserver.TCPServer(('127.0.0.1',0),SMTP) as server:
        thread=threading.Thread(target=server.serve_forever,daemon=True);thread.start()
        for appenv,origin in [('development','http://localhost:8080'),('staging','https://staging.stridebr.com.br'),('production','https://stridebr.com.br')]:
            for support,expected in [('support@example.invalid','support@example.invalid'),('',None),('invalid',None),('FIXTURE@example.invalid',None),('bad@example.invalid\r\nBcc: injected@example.invalid',None)]:
                env={**os.environ,'STRIDEBR_ENV_FILE':'/dev/null','STRIDEBR_APP_ENV':appenv,'STRIDEBR_APP_URL':origin,'STRIDEBR_MAIL_TRANSPORT':'smtp','STRIDEBR_MAIL_FROM':'fixture@example.invalid','STRIDEBR_MAIL_FROM_NAME':'StrideBR','STRIDEBR_SUPPORT_EMAIL':support,'STRIDEBR_SMTP_HOST':'localhost','STRIDEBR_SMTP_PORT':str(server.server_address[1]),'STRIDEBR_SMTP_AUTH':'0','STRIDEBR_SMTP_ENCRYPTION':'tls'}
                code="require 'src/includes/mail.php'; exit(stridebr_send_mail('recipient@example.invalid','StrideBR fixture',stridebr_app_url().'/reset-password.php?token=fixture')?0:1);"
                result=subprocess.run(['php','-d','openssl.cafile='+str(cert),'-r',code],cwd=ROOT,env=env,capture_output=True)
                assert result.returncode==0,(appenv,result.stderr.decode())
                assert origin.encode() in received[-1]
                message=BytesParser(policy=policy.default).parsebytes(received[-1])
                assert str(message['From'])=='StrideBR <fixture@example.invalid>'
                assert message['Reply-To']==expected,(appenv,support,message['Reply-To'])
                assert message['Bcc'] is None
        server.shutdown()
    assert len(received)==15
print('PASS SMTP STARTTLS with verified local certificate in development/staging/production; 15 messages captured locally; valid, empty, invalid, same-sender and header-injection Reply-To cases')
