#!/usr/bin/env python3
"""Single-message SMTP sink for local integration tests; binds only to loopback."""
import socket
import sys

if len(sys.argv) != 3:
    raise SystemExit("usage: fake_smtp.py OUTPUT.eml PORT")
out_path = sys.argv[1]
port = int(sys.argv[2])
if port < 1 or port > 65535:
    raise SystemExit("invalid port")

server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
server.bind(("127.0.0.1", port))
server.listen(1)
client, _ = server.accept()
reader = client.makefile("rb")

def reply(line):
    client.sendall((line + "\r\n").encode("ascii"))

reply("220 localhost.test ESMTP ready")
message_lines = []
while True:
    raw = reader.readline()
    if not raw:
        break
    command = raw.decode("utf-8", "replace").rstrip("\r\n").split(" ", 1)[0].upper()
    if command in ("EHLO", "HELO"):
        reply("250-localhost.test")
        reply("250 SIZE 10485760")
    elif command == "MAIL":
        reply("250 2.1.0 sender ok")
    elif command == "RCPT":
        reply("250 2.1.5 recipient ok")
    elif command in ("RSET", "NOOP"):
        reply("250 ok")
    elif command == "DATA":
        reply("354 end with <CRLF>.<CRLF>")
        while True:
            raw = reader.readline()
            if not raw or raw in (b".\r\n", b".\n"):
                break
            if raw.startswith(b".."):
                raw = raw[1:]
            message_lines.append(raw)
        with open(out_path, "wb") as output:
            output.write(b"".join(message_lines))
        reply("250 2.0.0 captured locally")
    elif command == "QUIT":
        reply("221 bye")
        break
    elif command == "AUTH":
        reply("535 authentication disabled in local test sink")
    else:
        reply("502 unsupported in local test sink")

client.close()
server.close()
