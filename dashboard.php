<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Honeypot Cybersecurity Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
  <style>
    @keyframes glow {
  0% { text-shadow: 0 0 5px #00ffe0; }
  100% { text-shadow: 0 0 20px #00ffe0, 0 0 40px #00ffe0; }
}
@keyframes fadeOut {
  to { opacity: 0; visibility: hidden; }
}
@keyframes glitch {
  0% { transform: translate(0); }
  20% { transform: translate(-2px, 2px); }
  40% { transform: translate(2px, -2px); }
  60% { transform: translate(-1px, 1px); }
  80% { transform: translate(1px, -1px); }
  100% { transform: translate(0); }
}

    body {
      background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
      color: #ffffff;
      font-family: 'Segoe UI', sans-serif;
      overflow-x: hidden;
    }
    h2, h5 {
      font-weight: bold;
      color: #00ffe0;
    }
    .card {
      border-radius: 15px;
      box-shadow: 0 8px 20px rgba(0,255,255,0.2);
      background-color: #1b2735;
      color: #ffffff;
      transition: transform 0.3s ease;
    }
    .card:hover {
      transform: scale(1.02);
    }
    .table th {
      background-color: #00c3ff44;
    }
    .btn {
      font-size: 0.85rem;
    }
    code {
      color: #00ffe0;
    }
  </style>
</head>
<body>
  <div class="container py-4">
    <h2 class="text-center mb-5" id="main-title">🚨 Honeypot Cybersecurity Dashboard</h2>

    <div class="row g-4">
      <div class="col-md-4">
        <div class="card p-3"><h5>📊 Attack Type Distribution</h5><canvas id="attackChart"></canvas></div>
      </div>
      <div class="col-md-4">
        <div class="card p-3"><h5>⏱️ Suspicious Activity (12h)</h5><canvas id="timeSeriesChart"></canvas></div>
      </div>
      <div class="col-md-4">
        <div class="card p-3"><h5>🔐 SSH Login Status</h5><canvas id="sshChart"></canvas></div>
      </div>
      <div class="col-md-4">
        <div class="card p-3"><h5>🚨 Top Scanners (PSAD)</h5><canvas id="psadTopChart"></canvas></div>
      </div>
      <div class="col-md-4">
        <div class="card p-3"><h5>🧠 PSAD Summary</h5><canvas id="psadSummaryChart"></canvas></div>
      </div>
      <div class="col-md-4">
        <div class="card p-3"><h5>🛠️ Port Scan Activity</h5><canvas id="portScanChart"></canvas></div>
      </div>
    </div>

    <div class="dashboard-section mt-5">
      <div class="card p-4">
        <h5>🕵️ SSH Command Logs (Last 10)</h5>
        <div class="table-responsive">
          <table class="table table-striped table-dark">
            <thead><tr><th>#</th><th>IP Address</th><th>Command</th><th>Timestamp</th></tr></thead>
            <tbody id="ssh-command-table">
              <tr><td colspan="4" class="text-center">⏳ Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="dashboard-section mt-5">
      <div class="card p-4">
        <h5>🧪 Review Anomalous Login Logs</h5>
        <div class="table-responsive">
          <table class="table table-bordered table-dark" id="login-review-table">
            <thead><tr><th>ID</th><th>Username</th><th>IP</th><th>Device</th><th>Prediction</th><th>Time</th><th>Review</th></tr></thead>
            <tbody><tr><td colspan="7" class="text-center">⏳ Loading...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="dashboard-section mt-5">
  <div class="card p-4">
    <h5>🌐 Network Activity Logs</h5>
    <div class="table-responsive">
      <table class="table table-striped table-dark" id="attacker-activity-table">
        <thead>
          <tr>
            <th>#</th>
            <th>IP Address</th>
            <th>Port</th>
            <th>Protocol</th>
            <th>Scan Type</th>
            <th>Timestamp</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="6" class="text-center">⏳ Loading...</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
<!-- 💀 Hacked-Style Cybersecurity Intro Animation -->
<!-- 🧠 Honeypot Cybersecurity Intro Animation -->
<div id="introOverlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:#000;z-index:9999;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:'Courier New', monospace;text-align:center;color:#00ffe0;animation:fadeOut 1s ease-out 7.8s forwards;">
  <h1 id="mainTitle" style="font-size:3.2rem;text-shadow:0 0 15px #00ffe0;margin-bottom:20px;">HoneyPot – A Cybersecurity Project </h1>

  <div style="color:#00ffe0; animation:glow 1.5s infinite alternate;">
    <div><strong>👨‍💻 Developers</strong> &nbsp; 📧 Emails@gmail.com</div>

    <div><strong>👨‍💻 Arman Kumar </strong> &nbsp; 📧 armank8000@gmail.com</div>
    <div><strong>👨‍💻 Himanshu Kumar</strong> &nbsp; 📧 krhimanshu2103@gmail.com</div>
  </div>

  <div style="color:#00ffe0; font-size:0.95rem; margin-top:30px;" id="typingEffect">Initializing HoneyPot... █</div>

  <audio id="typeSound" src="https://www.soundjay.com/mechanical/typewriter-1.wav" preload="auto"></audio>
</div>

<script>
  const typingLines = [
    "Initializing HoneyPot Modules...",
    "Establishing SSH Watchdog...",
    "Deploying Port Scanner Bait...",
    "Machine Learning Predictor Online...",
    "Dashboard Ready. Welcome Agent."
  ];

  let line = 0, char = 0;
  const typingTarget = document.getElementById("typingEffect");
  const typeSound = document.getElementById("typeSound");

  function playSound() {
    typeSound.currentTime = 0;
    typeSound.play().catch(() => {});
  }

  function typeNext() {
    if (line < typingLines.length) {
      if (char < typingLines[line].length) {
        typingTarget.innerHTML = typingLines[line].substring(0, char + 1) + ' █';
        playSound();
        char++;
        setTimeout(typeNext, 35);
      } else {
        char = 0;
        line++;
        setTimeout(typeNext, 1000);
      }
    } else {
      typingTarget.innerHTML = "✔ All Systems Online. Honeypot Ready █";
    }
  }

  window.addEventListener('load', () => {
    setTimeout(typeNext, 500);
  });
</script>




  <script>
    let charts = {};

    async function fetchData() {
      const res = await fetch("get_graph_data.php");
      return await res.json();
    }

    function createOrUpdateChart(id, type, labels, data, options = {}) {
      if (charts[id]) {
        charts[id].data.labels = labels;
        charts[id].data.datasets[0].data = data;
        charts[id].update();
      } else {
        charts[id] = new Chart(document.getElementById(id), {
          type,
          data: {
            labels,
            datasets: [{
              label: 'Count',
              data,
              backgroundColor: ['#00ffe0', '#ff6b6b', '#1dd1a1', '#5f27cd'],
              borderColor: '#fff',
              borderWidth: 1
            }]
          },
          options
        });
      }
    }

    function renderCharts(data) {
      createOrUpdateChart('attackChart', 'doughnut', Object.keys(data.attack_types), Object.values(data.attack_types));
      createOrUpdateChart('timeSeriesChart', 'line', data.time_series.labels, data.time_series.data, {
        scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } }
      });
      createOrUpdateChart('sshChart', 'pie', Object.keys(data.ssh_logins), Object.values(data.ssh_logins));
      createOrUpdateChart('psadTopChart', 'bar', data.psad.top_ips.map(i => i.ip), data.psad.top_ips.map(i => i.count));
      createOrUpdateChart('psadSummaryChart', 'bar', ['Sources','Blocked'], [data.psad.total_sources,data.psad.blocked_count]);
      createOrUpdateChart('portScanChart', 'bar', data.port_scans.map(i => `${i.ip_address} ${i.protocol}/${i.port}`), data.port_scans.map(() => 1));
    }

    function renderSSHCommands(data) {
      const table = document.querySelector("#ssh-command-table");
      table.innerHTML = "";
      if (!data.ssh_commands || data.ssh_commands.length === 0) {
        table.innerHTML = `<tr><td colspan="4" class="text-center">✅ No SSH command activity yet.</td></tr>`;
        return;
      }
      data.ssh_commands.forEach((entry, idx) => {
        const row = document.createElement("tr");
        row.innerHTML = `<td>${idx + 1}</td><td>${entry.ip_address}</td><td><code>${entry.command}</code></td><td>${entry.timestamp}</td>`;
        table.appendChild(row);
      });
    }

    async function loadLoginLogs() {
      try {
        const res = await fetch("review_log.php");
        const data = await res.json();
        const tbody = document.querySelector("#login-review-table tbody");
        tbody.innerHTML = "";

        if (!data.logs || data.logs.length === 0) {
          tbody.innerHTML = `<tr><td colspan="7" class="text-center">✅ All reviewed.</td></tr>`;
          return;
        }

        data.logs.forEach(log => {
          const row = document.createElement("tr");
          row.innerHTML = `
            <td>${log.id}</td>
            <td>${log.username}</td>
            <td>${log.ip}</td>
            <td>${log.device_type}</td>
            <td>${log.prediction}</td>
            <td>${log.created_at}</td>
            <td>
              <button class="btn btn-sm btn-success" onclick="submitLoginReview(${log.id}, 'normal')">Normal</button>
              <button class="btn btn-sm btn-danger" onclick="submitLoginReview(${log.id}, 'suspicious')">Suspicious</button>
            </td>`;
          tbody.appendChild(row);
        });
      } catch (err) {
        console.error("Login log fetch error:", err);
      }
    }

    function submitLoginReview(id, label) {
      fetch("review_log.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({id, label})
      })
      .then(res => res.json())
      .then(() => loadLoginLogs());
    }
    async function loadAttackerActivity() {
  try {
    const res = await fetch("get_graph_data.php");
    const data = await res.json();
    const tbody = document.querySelector("#attacker-activity-table tbody");
    tbody.innerHTML = "";

    if (!data.port_scans || data.port_scans.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center">✅ No activity yet.</td></tr>`;
      return;
    }

    data.port_scans.forEach((act, index) => {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>${index + 1}</td>
        <td><code>${act.ip_address}</code></td>
        <td><span class="badge bg-info">${act.port}</span></td>
        <td><span class="badge bg-secondary">${act.protocol}</span></td>
        <td><span class="badge bg-warning text-dark">${act.scan_type}</span></td>
        <td>${act.timestamp}</td>
      `;
      tbody.appendChild(row);
    });
  } catch (err) {
    console.error("Attacker activity fetch error:", err);
  }
}


    async function refreshAll() {
      const data = await fetchData();
      renderCharts(data);
      loadAttackerActivity();
      renderSSHCommands(data);
      loadLoginLogs();
    }

    refreshAll();
    setInterval(refreshAll, 5000);

    anime({
      targets: '#main-title',
      translateY: [-20, 0],
      opacity: [0, 1],
      easing: 'easeOutExpo',
      duration: 1500
    });
  </script>
</body>
</html>