import socket
import pymysql
from datetime import datetime
import requests
import json

# === CONFIG ===
HOST = '0.0.0.0'
PORT = 2222

DB_HOST = 'localhost'
DB_USER = 'root'
DB_PASS = ''
DB_NAME = 'honeypot_db'

# === Log to ssh_logs table ===
def log_ssh_attempt(ip, username, success):
    try:
        conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME)
        with conn.cursor() as cursor:
            cursor.execute("""
                INSERT INTO ssh_logs (ip_address, username, success, timestamp)
                VALUES (%s, %s, %s, %s)
            """, (ip, username, success, datetime.now().strftime('%Y-%m-%d %H:%M:%S')))
            conn.commit()
    except Exception as e:
        print("[!] DB SSH log error:", e)
    finally:
        conn.close()

# === Send to attack_log.php for prediction and review ===
def send_attack_metadata(ip, username):
    try:
        requests.post("http://localhost/attack_log.php", data={
            'username': username,
            'honeypot_id': 'ssh-honeypot',
        }, timeout=5)
    except Exception as e:
        print("[!] attack_log.php failed:", e)

# === Log successful attacker commands ===
def log_attacker_command(ip, command):
    try:
        conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME)
        with conn.cursor() as cursor:
            cursor.execute("""
                INSERT INTO ssh_command_logs (ip_address, command, timestamp)
                VALUES (%s, %s, %s)
            """, (ip, command, datetime.now().strftime('%Y-%m-%d %H:%M:%S')))
            conn.commit()
    except Exception as e:
        print("[!] Command log error:", e)
    finally:
        conn.close()

# === Fake shell response simulation ===
def simulate_command(cmd):
    responses = {
        'ls': 'index.html\nadmin.php\nlog.txt\n',
        'pwd': '/home/admin',
        'whoami': 'admin',
        'uname -a': 'Linux honeypot 5.10.0-kali #1 SMP x86_64 GNU/Linux',
        'ps aux': 'root 1 0.0 0.1 /sbin/init\nadmin 2345 0.0 0.3 /bin/bash',
        'ifconfig': 'eth0: inet 192.168.1.10  netmask 255.255.255.0',
        'netstat -tulnp': 'tcp 0 0 0.0.0.0:22 LISTEN 1234/sshd',
        'cat /etc/passwd': 'root:x:0:0:root:/root:/bin/bash\nadmin:x:1000:1000::/home/admin:/bin/bash',
    }
    return responses.get(cmd.strip(), f"bash: {cmd.strip()}: command not found")

# === SSH Session Handling ===
def handle_client(client_socket, ip):
    try:
        client_socket.sendall(b"SSH-2.0-OpenSSH_8.2p1 Ubuntu-4ubuntu0.3\r\n")
        client_socket.sendall(b"login as: ")
        username = client_socket.recv(1024).decode(errors='ignore').strip()

        client_socket.sendall(b"Password: ")
        password = client_socket.recv(1024).decode(errors='ignore').strip()

        success = (username == 'admin' and password == 'admin')

        # Log attempt + Send to attack_log.php
        log_ssh_attempt(ip, username, int(success))
        send_attack_metadata(ip, username)

        if success:
            client_socket.sendall(b"\nWelcome to Ubuntu 20.04.6 LTS\nadmin@honeypot:~$ ")
            while True:
                command = client_socket.recv(2048).decode(errors='ignore').strip()
                if not command:
                    break
                print(f"[~] {ip} executed: {command}")
                log_attacker_command(ip, command)
                response = simulate_command(command) + "\nadmin@honeypot:~$ "
                client_socket.sendall(response.encode())
        else:
            client_socket.sendall(b"Permission denied, please try again.\n")
            client_socket.close()

    except Exception as e:
        print("[!] SSH session error:", e)
    finally:
        client_socket.close()

# === Honeypot Start ===
def start_honeypot():
    print(f"[*] SSH Honeypot listening on {PORT}")
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    server.bind((HOST, PORT))
    server.listen(5)

    while True:
        client, addr = server.accept()
        print(f"[+] Connection from {addr[0]}")
        handle_client(client, addr[0])

if __name__ == '__main__':
    start_honeypot()
import socket
import pymysql
from datetime import datetime
import requests
import json

# === CONFIG ===
HOST = '0.0.0.0'
PORT = 2222

DB_HOST = 'localhost'
DB_USER = 'root'
DB_PASS = ''
DB_NAME = 'honeypot_db'

# === Log to ssh_logs table ===
def log_ssh_attempt(ip, username, success):
    try:
        conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME)
        with conn.cursor() as cursor:
            cursor.execute("""
                INSERT INTO ssh_logs (ip_address, username, success, timestamp)
                VALUES (%s, %s, %s, %s)
            """, (ip, username, success, datetime.now().strftime('%Y-%m-%d %H:%M:%S')))
            conn.commit()
    except Exception as e:
        print("[!] DB SSH log error:", e)
    finally:
        conn.close()

# === Send to attack_log.php for prediction and review ===
def send_attack_metadata(ip, username):
    try:
        requests.post("http://localhost/honeypod/log_attack.php", data={
            'username': username,
            'honeypot_id': 'ssh-honeypot',
        }, timeout=5)
    except Exception as e:
        print("[!] attack_log.php failed:", e)

# === Log successful attacker commands ===
def log_attacker_command(ip, command):
    try:
        conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME)
        with conn.cursor() as cursor:
            cursor.execute("""
                INSERT INTO ssh_command_logs (ip_address, command, timestamp)
                VALUES (%s, %s, %s)
            """, (ip, command, datetime.now().strftime('%Y-%m-%d %H:%M:%S')))
            conn.commit()
    except Exception as e:
        print("[!] Command log error:", e)
    finally:
        conn.close()

# === Fake shell response simulation ===
def simulate_command(cmd):
    responses = {
        'ls': 'index.html\nadmin.php\nlog.txt\n',
        'pwd': '/home/admin',
        'whoami': 'admin',
        'uname -a': 'Linux honeypot 5.10.0-kali #1 SMP x86_64 GNU/Linux',
        'ps aux': 'root 1 0.0 0.1 /sbin/init\nadmin 2345 0.0 0.3 /bin/bash',
        'ifconfig': 'eth0: inet 192.168.1.10  netmask 255.255.255.0',
        'netstat -tulnp': 'tcp 0 0 0.0.0.0:22 LISTEN 1234/sshd',
        'cat /etc/passwd': 'root:x:0:0:root:/root:/bin/bash\nadmin:x:1000:1000::/home/admin:/bin/bash',
    }
    return responses.get(cmd.strip(), f"bash: {cmd.strip()}: command not found")

# === SSH Session Handling ===
def handle_client(client_socket, ip):
    try:
        client_socket.sendall(b"SSH-2.0-OpenSSH_8.2p1 Ubuntu-4ubuntu0.3\r\n")
        client_socket.sendall(b"login as: ")
        username = client_socket.recv(1024).decode(errors='ignore').strip()

        client_socket.sendall(b"Password: ")
        password = client_socket.recv(1024).decode(errors='ignore').strip()

        success = (username == 'admin' and password == 'admin')

        # Log attempt + Send to attack_log.php
        log_ssh_attempt(ip, username, int(success))
        send_attack_metadata(ip, username)

        if success:
            client_socket.sendall(b"\nWelcome to Ubuntu 20.04.6 LTS\nadmin@honeypot:~$ ")
            while True:
                command = client_socket.recv(2048).decode(errors='ignore').strip()
                if not command:
                    break
                print(f"[~] {ip} executed: {command}")
                log_attacker_command(ip, command)
                response = simulate_command(command) + "\nadmin@honeypot:~$ "
                client_socket.sendall(response.encode())
        else:
            client_socket.sendall(b"Permission denied, please try again.\n")
            client_socket.close()

    except Exception as e:
        print("[!] SSH session error:", e)
    finally:
        client_socket.close()

# === Honeypot Start ===
def start_honeypot():
    print(f"[*] SSH Honeypot listening on {PORT}")
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    server.bind((HOST, PORT))
    server.listen(5)

    while True:
        client, addr = server.accept()
        print(f"[+] Connection from {addr[0]}")
        handle_client(client, addr[0])

if __name__ == '__main__':
    start_honeypot()
