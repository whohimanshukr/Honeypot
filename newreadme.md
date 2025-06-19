A complete system for tracking real-time SSH login attempts and port scans using Python, PHP, MySQL, and PSAD.

---

## ✅ 1. Requirements

- A Linux server (Ubuntu/Debian recommended)
- psad installed and running
- Apache + PHP (XAMPP or LAMP stack)
- MySQL/MariaDB database
- sudo privileges
- pdo_mysql enabled in PHP

---

## 🔧 2. Install PSAD

```bash
sudo apt update
sudo apt install psad
sudo systemctl enable psad
sudo systemctl start psad
```

Enable UFW if not already:
```bash
sudo ufw enable
```

---

## 🔧 3. Allow Apache to Run PSAD

Give Apache permission to run `psad` commands:

```bash
sudo visudo
```

Add at the bottom:
```bash
www-data ALL=(ALL) NOPASSWD: /usr/sbin/psad
```

To check your Apache user:
```bash
ps aux | grep apache
```

---

## 🐍 4. Install Python Requirements

```bash
sudo apt update
pip3 install pymysql
```

---

## 🗂️ 5. Set Correct SSH Log File Path

- **Ubuntu/Debian**: `/var/log/auth.log`
- **CentOS/RHEL**: `/var/log/secure`

In your Python script:
```python
LOG_FILE = "/var/log/auth.log"
```

---

## 🔐 6. Grant Read Access to SSH Log File

```bash
sudo usermod -aG adm www-data
sudo chmod +r /var/log/auth.log
```

Or simply run your Python script with `sudo`:
```bash
sudo python3 ssh_log_watcher.py
```

---

## ⚙️ 7. Run the Script in Background

### Option A: Using tmux (Recommended)
```bash
sudo apt install tmux

# Start session
tmux new -s sshwatcher
python3 ssh_log_watcher.py

# Detach
Ctrl + B, then D
```

### Option B: Using nohup
```bash
nohup python3 ssh_log_watcher.py > ssh_log.txt 2>&1 &
```

---

## ⏱️ 8. Auto Fetch PSAD Data to MySQL

### `fetch_psad_data.php`
Put this script in `/var/www/html/` and add DB credentials via `db.php`.

### Add Cron Job:
```bash
sudo crontab -e
```
```bash
*/1 * * * * php /var/www/html/fetch_psad_data.php
```

This keeps inserting PSAD scan logs every minute.

---

## 🔁 9. Move Real SSH to Custom Port (If You Want to Run Honeypot on Port 22)

```bash
sudo nano /etc/ssh/sshd_config
```
Change:
```bash
Port 8822
```
Then:
```bash
sudo systemctl restart ssh
sudo ufw allow 8822/tcp
```

Before closing your terminal, verify:
```bash
ssh user@your-ip -p 8822
```

---

## 🧪 10. Expose Port 22 for Honeypot

Once real SSH uses a different port (e.g. 8822), port 22 becomes available for the honeypot.

---

## 🐍 11. Run Python Honeypot Server on Port 22

Make sure:
- Port 22 is free (real SSH moved)
- Firewall allows port 22
- Run the Python script with root privileges:

```bash
sudo python3 honeypot.py
```

This Python server mimics SSH login prompts, logs credentials and IPs, and sends them to MySQL just like the PHP `index.html` form.

---

## 🔍 12. Verify

Try from another machine:
```bash
ssh fakeuser@your-server-ip
```

You should see the attack logged in your MySQL database.

---

## ✅ Summary Table

| Task                               | Command/Notes                                  |
|------------------------------------|------------------------------------------------|
| Install PSAD                       | `sudo apt install psad`                        |
| Enable PSAD                        | `sudo systemctl enable psad`                  |
| Move real SSH port                | Edit `sshd_config`, use Port 8822              |
| Run Python honeypot on port 22     | `sudo python3 honeypot.py`                     |
| Apache user runs psad              | `visudo` entry for `www-data`                  |
| SSH log monitor runs continuously | `tmux` or `nohup`                              |
| PSAD logs to MySQL                 | PHP cron job every minute                      |

---