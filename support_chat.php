<?php
require_once __DIR__ . '/db.php';
$U = current_user();
$pageTitle = 'الدعم';
include __DIR__ . '/header.php'; ?>

<div class="ai-wrap">
  <div class="ai-head">
    <div class="ai-avatar">🧑‍💼</div>
    <div>
      <h2>الدعم الفني</h2>
      <p class="muted small">اكتب مشكلتك ورح يردّ عليك موظف من فريقنا</p>
    </div>
  </div>

  <?php if (!$U): ?>
    <div class="ai-messages"><div class="ai-msg bot"><div class="ai-bubble">
      لازم تسجّل دخول حتى تتواصل مع الدعم. <a href="/auth.php">سجّل دخول</a>
    </div></div></div>
  <?php else: ?>
  <div class="ai-messages" id="scMessages">
    <div class="ai-msg bot"><div class="ai-bubble">
      أهلاً 👋 اكتب رسالتك للدعم ورح نردّ عليك بأقرب وقت. ردّ الموظف رح يظهر هون مباشرةً.
    </div></div>
  </div>

  <div class="ai-input-bar">
    <input type="text" id="scInput" placeholder="اكتب رسالتك للدعم..." onkeydown="if(event.key==='Enter')scSend()">
    <button class="btn" id="scSend" onclick="scSend()">إرسال</button>
  </div>
  <?php endif; ?>
</div>

<?php if ($U): ?>
<script>
let scLastId = 0;

function scEsc(s){ return String(s).replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function scAdd(sender, body) {
  const box = document.getElementById('scMessages');
  const div = document.createElement('div');
  div.className = 'ai-msg ' + (sender === 'admin' ? 'bot' : 'user');
  const label = sender === 'admin' ? '<div class="ai-support-label">🧑‍💼 الدعم</div>' : '';
  div.innerHTML = '<div class="ai-bubble ' + (sender === 'admin' ? 'support' : '') + '">' + label + scEsc(body).replace(/\n/g, '<br>') + '</div>';
  box.appendChild(div);
  box.scrollTop = box.scrollHeight;
}

async function scPoll() {
  try {
    const res = await fetch('/support.php?action=fetch&after=' + scLastId, { credentials: 'same-origin' });
    const d = await res.json();
    if (d.ok && d.messages) {
      d.messages.forEach(m => {
        if (m.sender === 'admin') scAdd('admin', m.body); // رسائل المستخدم معروضة أصلاً
        scLastId = m.id;
      });
    }
  } catch (e) {}
}

async function scSend() {
  const input = document.getElementById('scInput');
  const btn = document.getElementById('scSend');
  const q = input.value.trim();
  if (!q) return;
  input.value = '';
  scAdd('user', q);
  btn.disabled = true;
  try {
    await fetch('/support.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
      body: JSON.stringify({ action: 'send', message: q }),
    });
  } catch (e) {}
  btn.disabled = false;
  input.focus();
}

document.addEventListener('DOMContentLoaded', async () => {
  // محادثة جديدة كل مرة: نحذف القديمة عند الفتح
  try { await fetch('/support.php?action=open', { credentials: 'same-origin' }); } catch (e) {}
  setInterval(scPoll, 8000);
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
