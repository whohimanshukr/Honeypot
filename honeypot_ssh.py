import socket
import pymysql
import requests
from datetime import datetime

# === CONFIGURATION ===
HOST = '0.0.0.0'
PORT = 229
HONEYPOT_ID = 'honeypot1'
STATIC_USERNAME = 'admin'
STATIC_PASSWORD = 'admin'

# === MySQL Configuration ===
DB_HOST = 'localhost'
DB_USER = 'root'
DB_PASS = ''
DB_NAME = 'honeypot_db'

# === PHP Endpoint to report login ===
ATTACK_LOG_URL = 'http://localhost/honeypod/log_attack.php'  # Change as needed


def log_ssh_attempt(ip, username, success):
    try:
        conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME)
        with conn.cursor() as cursor:
            timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            cursor.execute("""
                INSERT INTO ssh_logs (ip, username, success, timestamp)
                VALUES (%s, %s, %s, %s)
            """, (ip, username, int(success), timestamp))
            conn.commit()
            print(f"[+] SSH login {'SUCCESS' if success else 'FAIL'} for {ip}")
    except Exception as e:
        print("[!] DB error (ssh_logs):", e)
    finally:
        conn.close()


def report_to_php(ip, username):
    try:
        data = {
            'username': username,
            'honeypot_id': HONEYPOT_ID
        }
        headers = {'X-Forwarded-For': ip}  # Send real IP if behind proxy
        response = requests.post(ATTACK_LOG_URL, data=data, headers={
    'User-Agent': 'FakeSSH/1.0',
    'X-Forwarded-For': ip  # <- make sure to pass attacker's IP
}, timeout=3)
        print("[+] attack_log.php response:", response.text)
    except Exception as e:
        print("[!] Could not report to PHP backend:", e)


def log_command(ip_address, command):
    try:
        conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME)
        with conn.cursor() as cursor:
            timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            cursor.execute("""
                INSERT INTO ssh_command_logs (ip_address, timestamp, command)
                VALUES (%s, %s, %s)
            """, (ip_address, timestamp, command))
            conn.commit()
            print(f"[+] Logged command from {ip_address}: {command}")
    except Exception as e:
        print("[!] DB error (ssh_command_logs):", e)
    finally:
        conn.close()


FAKE_RESPONSES = {
    "":"",
    "ls": "Desktop  Documents  Downloads  Music  Pictures  Public  Templates  Videos",
    "pwd": "/home/admin",
    "whoami": "admin",
    "uname -a": "Linux ubuntu 5.4.0-91-generic #102-Ubuntu SMP x86_64 GNU/Linux",
    "ifconfig": "eth0: flags=4163<UP,BROADCAST,RUNNING,MULTICAST>  inet 192.168.1.10",
    "id": "uid=1000(admin) gid=1000(admin) groups=1000(admin)",
    "ps aux": "root       1  0.0  0.1  22568  1024 ?        Ss   10:00   0:00 /sbin/init",
    "uptime": "10:35:48 up  2:13,  1 user,  load average: 0.00, 0.01, 0.05",
    "df -h": "/dev/sda1        100G   35G   61G  37% /",
    "top -b -n 1": "Tasks: 87 total,   1 running, 86 sleeping, 0 stopped, 0 zombie",
    "free -m": "Mem:  7987   1024   5632   123  1530   234",
    "netstat -tulnp": "tcp        0      0 0.0.0.0:22            0.0.0.0:*               LISTEN      897/sshd",
    "history": "1 ls\n2 pwd\n3 whoami\n4 cd /etc\n5 cat passwd",
    "cat /etc/passwd": "root:x:0:0:root:/root:/bin/bash\nadmin:x:1000:1000:Admin:/home/admin:/bin/bash",
    "cat /etc/shadow": "root:$6$randomsalt$hashedpassword:::::::\nadmin:$6$othersalt$hashedpass:::::::",
    "cd /": "",
    "cd /var": "",
    "cd /home": "",
    "cd /etc": "",
    "ls -la": "drwxr-xr-x  3 admin admin 4096 Jun 18 10:10 .\ndrwxr-xr-x  5 root  root  4096 Jun 18 09:50 ..",
    "cat ~/.bashrc": "# .bashrc\nexport PS1='\\u@\\h:\\w$ '\nalias ll='ls -la'",
    "alias": "alias ll='ls -la'\nalias grep='grep --color=auto'",
    "env": "SHELL=/bin/bash\nUSER=admin\nHOME=/home/admin",
    "lsblk": "NAME   MAJ:MIN RM  SIZE RO TYPE MOUNTPOINT\nsda      8:0    0  100G  0 disk /\nsda1     8:1    0  100G  0 part /",
    "mount": "/dev/sda1 on / type ext4 (rw,relatime,errors=remount-ro)",
    "crontab -l": "# m h  dom mon dow   command\n0 2 * * * /usr/bin/updatedb",
    "uname -r": "5.4.0-91-generic",
    "dmesg | tail": "[    0.000000] Linux version 5.4.0-91-generic",
    "ls /var/log": "auth.log  boot.log  syslog  dmesg  kern.log  apt  journal",
    "cat /var/log/auth.log": "Jun 18 10:12:30 ubuntu sshd[897]: Accepted password for admin from 192.168.0.5 port 44432 ssh2",
    "ip a": "1: lo: <LOOPBACK> mtu 65536 qdisc noqueue state UNKNOWN group default\n2: eth0: <BROADCAST,MULTICAST,UP,LOWER_UP>",
    "journalctl -xe": "-- Logs begin at Tue 2025-06-18 10:00:00 IST, end at Tue 2025-06-18 12:00:00 IST",
    "ls /etc/systemd/system": "multi-user.target.wants  sshd.service  cron.service",
    "ping -c 4 google.com": "PING google.com (142.250.182.110) 56(84) bytes of data.\n--- google.com ping statistics ---",
    "wget http://example.com": "--2025-06-18--  http://example.com\nResolving example.com...",
    "curl ifconfig.me": "192.168.1.10",
    "hostname": "ubuntu",
    "groups": "admin sudo",
    "who": "admin  tty1         2025-06-18 10:00 (:0)",
    "last": "admin  pts/0        192.168.0.5     Tue Jun 18 10:00 - 10:30  (00:30)",
    "ls /tmp": "temp1.txt  session123.sock  cache/",
    "cat /proc/cpuinfo": "processor   : 0\nvendor_id   : GenuineIntel\nmodel name  : Intel(R) Core(TM) i5",
    "cat /proc/meminfo": "MemTotal:       8175304 kB\nMemFree:        6432836 kB",
    "lscpu": "Architecture: x86_64\nCPU(s):              4\nModel name:          Intel(R) Core(TM) i5",
    "uptime -p": "up 2 hours, 13 minutes",
    "lsmod": "Module                  Size  Used by\nx_tables               32768  1 ip_tables",
    "iptables -L": "Chain INPUT (policy ACCEPT)\nChain FORWARD (policy ACCEPT)\nChain OUTPUT (policy ACCEPT)",
    "echo $PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin",
    "du -sh *": "100M  Documents\n200M  Downloads\n50M  Pictures",
    "tree": ".\n├── Documents\n│   └── resume.pdf\n├── Downloads\n└── Pictures",
    "find / -name id_rsa": "/home/admin/.ssh/id_rsa",
    "ssh-keygen": "Generating public/private rsa key pair.",
    "locate passwd": "/etc/passwd\n/usr/share/doc/passwd",
    "grep admin /etc/passwd": "admin:x:1000:1000:Admin:/home/admin:/bin/bash",
    "service --status-all": "[ + ]  ssh\n[ + ]  apache2\n[ - ]  mysql",
    "systemctl status ssh": "● ssh.service - OpenBSD Secure Shell server\n   Loaded: loaded (/lib/systemd/system/ssh.service; enabled)",
    "which python3": "/usr/bin/python3",
    "python3 --version": "Python 3.9.5",
    "java -version": "openjdk version \"11.0.11\" 2021-04-20",
    "gcc --version": "gcc (Ubuntu 9.3.0-17ubuntu1~20.04) 9.3.0",
    "dpkg -l": "ii  openssh-server 1:8.2p1-4ubuntu0.3 amd64",
    "rpm -qa": "bash-5.0.17-2.fc32.x86_64\nvim-common-8.2.3081-1.fc32.x86_64",
    "cat /etc/os-release": 'NAME="Ubuntu"\nVERSION="20.04.6 LTS (Focal Fossa)"',
    "ls /etc/init.d/": "ssh  cron  apache2",
    "whoami && id": "admin\nuid=1000(admin) gid=1000(admin) groups=1000(admin)",
    "cat ~/.ssh/id_rsa.pub": "ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQC...",
    "ls -l /root": "ls: cannot open directory '/root': Permission denied",
    "sudo -l": "User admin may run the following commands on ubuntu:\n    (ALL) ALL",
}


def simulate_shell(client_socket, ip):
    try:
        client_socket.sendall(b"login as: ")
        username = client_socket.recv(1024).decode().strip()

        client_socket.sendall(b"Password: ")
        password = client_socket.recv(1024).decode().strip()

        success = (username == STATIC_USERNAME and password == STATIC_PASSWORD)

        # Log login attempt
        log_ssh_attempt(ip, username, success)
        report_to_php(ip, username)

        if not success:
            client_socket.sendall(b"Permission denied, please try again.\n")
            client_socket.close()
            return

        client_socket.sendall(b"\nWelcome to Ubuntu 20.04.6 LTS\n")
        client_socket.sendall(b"\nadmin@ubuntu:~$ ")

        while True:
            command = client_socket.recv(1024).decode().strip()
            if not command:
                break
            log_command(ip, command)

            response = FAKE_RESPONSES.get(command, f"bash: {command}: command not found")
            client_socket.sendall((response + "\nadmin@ubuntu:~$ ").encode())

    except Exception as e:
        print("[!] SSH session error:", e)
    finally:
        client_socket.close()


def start_honeypot():
    print(f"[*] SSH honeypot listening on port {PORT}...")
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)

    try:
        sock.bind((HOST, PORT))
        sock.listen(5)
    except PermissionError:
        print("[!] Permission denied. Use sudo or switch to port >=1024.")
        return

    while True:
        client, addr = sock.accept()
        ip = addr[0]
        print(f"[~] Connection from {ip}")
        simulate_shell(client, ip)


if __name__ == '__main__':
    start_honeypot()
