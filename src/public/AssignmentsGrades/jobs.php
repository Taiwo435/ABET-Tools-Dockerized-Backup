<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/csrf.php';
require_login();
$csrfToken = csrf_token('tool1_proxy');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Job History | ABET Tools</title>
  <link rel="stylesheet" href="/assets/css/tool1.css">
  <link rel="stylesheet" href="/assets/css/jobs.css">
</head>
<body>

  <header class="site-header">
    <div class="site-title">ABET Tools - Job History</div>
    <div class="nav-links">
      <a href="select-courses.php" class="nav-link">New Extraction</a>
      <a href="/home" class="nav-link">← Back to Dashboard</a>
    </div>
  </header>

  <div class="main-container">

    <div class="page-header">
      <div class="page-title">
        <h1>Your Extraction Jobs</h1>
        <p>Recent extraction and formatting jobs</p>
      </div>
    </div>

    <div class="page-actions">
      <span id="lastUpdated" style="font-size:0.8rem;color:var(--text-light)"></span>
      <button class="refresh-btn" onclick="loadJobs()">↻ Refresh</button>
    </div>

    <div class="card">
      <div class="card-body" style="padding:0" id="jobsContainer">
        <div class="empty-state" id="loadingState">
          <span class="spinner"></span> Loading…
        </div>
      </div>
    </div>

  </div>

<script>
  let hasActiveJobs = false;

  function timeAgo(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'Z');
    const s = Math.floor((Date.now() - d.getTime()) / 1000);
    if (s < 60)    return s + 's ago';
    if (s < 3600)  return Math.floor(s / 60) + 'm ago';
    if (s < 86400) return Math.floor(s / 3600) + 'h ago';
    return Math.floor(s / 86400) + 'd ago';
  }

  function statusBadge(status, progress) {
    const label = status.charAt(0).toUpperCase() + status.slice(1);
    let extra = '';
    if (status === 'processing' && progress > 0) {
      extra = `<div class="progress-mini"><div class="progress-mini-fill" style="width:${progress}%"></div></div>`;
    }
    return `<span class="status-badge status-${status}">${label}</span>${extra}`;
  }

  function renderJobs(jobs) {
    const container = document.getElementById('jobsContainer');
    if (!jobs || jobs.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <h3>No jobs yet</h3>
          <p>Start an extraction from the <a href="extract.php">Extract Courses</a></p>
        </div>`;
      return;
    }

    hasActiveJobs = jobs.some(j => j.status === 'processing' || j.status === 'pending');

    let html = `<table class="jobs-table">
      <thead><tr><th>Course</th><th>Status</th><th>Message</th><th>Started</th></tr></thead><tbody>`;

    for (const j of jobs) {
      // WTF does this do???
      // search the params column, for which I have no idea what it stores.
      const params = typeof j.params === 'string' ? JSON.parse(j.params) : (j.params || {});
      // retrieve the metadata, which may also not even exist
      const meta = typeof j.result_meta === 'string' ? JSON.parse(j.result_meta || '{}') : (j.result_meta || {});
      // Quantum ahh course label
      const courseLabel = params.course_name || meta.course_folder_name || params.course_ids_to_pull?.[0] || j.id?.substring(0, 8);
      // Debug message from stuff.
      const msg = j.message || (j.status === 'failed' ? (j.error_message || '') : '');

      html += `<tr>
        <td><strong>${esc(courseLabel)}</strong></td>
        <td>${statusBadge(j.status, j.progress || 0)}</td>
        <td style="color:var(--text-light);font-size:0.85rem;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(msg)}</td>
        <td style="font-size:0.85rem;color:var(--text-light)">${timeAgo(j.created_at)}</td>
      </tr>`;
    }

    html += '</tbody></table>';
    container.innerHTML = html;
    document.getElementById('lastUpdated').textContent = 'Updated ' + new Date().toLocaleTimeString();
  }

  let polling = false;
  let csrfToken = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>';

  async function loadJobs() {
    if (polling) return;  // prevent overlapping requests
    polling = true;

    try {
      const body = new FormData();
      body.append('action', 'check-job-history');
      body.append('csrf_token', csrfToken);

      const res = await fetch('api-proxy.php', { method: 'POST', body });
      const data = await res.json();

      if (data.next_csrf) csrfToken = data.next_csrf;

      if (data.success) {
        renderJobs(data.jobs);
      } else {
        document.getElementById('jobsContainer').innerHTML =
          `<div class="empty-state"><h3>Error loading jobs</h3><p>${esc(data.message)}</p></div>`;
      }
    } catch (err) {
      document.getElementById('jobsContainer').innerHTML =
        `<div class="empty-state"><h3>Network error</h3></div>`;
    } finally {
      polling = false;
    }

    if (hasActiveJobs) setTimeout(loadJobs, 5000);
  }

  function esc(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  loadJobs();
</script>

</body>
</html>
