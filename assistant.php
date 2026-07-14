<?php
require_once __DIR__ . '/db.php';
$U = current_user();
$pageTitle = 'المساعد الذكي';
include __DIR__ . '/header.php'; ?>

<div class="ai-wrap">
  <div class="ai-head">
    <div class="ai-avatar">🤖</div>
    <div>
      <h2>المساعد الذكي</h2>
      <p class="muted small">اسألني عن الألعاب، الشحن، الأسعار، وطرق الدفع</p>
    </div>
  </div>

  <div class="ai-messages" id="aiMessages">
    <div class="ai-msg bot">
      <div class="ai-bubble">
        أهلاً فيك في <?= e(STORE_NAME) ?>! 👋<br>
        أنا المساعد الذكي تبع المتجر. اسألني عن أي شي يخص:
        شحن الألعاب (ببجي، فري فاير...)، الأسعار، طرق الدفع (سيرياتيل كاش، شام كاش)، أو كيف تطلب.
      </div>
    </div>
  </div>

  <div class="ai-suggestions" id="aiSuggestions">
    <button onclick="askSuggestion(this)">كيف أشحن محفظتي؟</button>
    <button onclick="askSuggestion(this)">كيف أطلب شدات ببجي؟</button>
    <button onclick="askSuggestion(this)">شو طرق الدفع المتاحة؟</button>
    <button onclick="askSuggestion(this)">كم بياخد وقت التسليم؟</button>
    <button onclick="askSuggestion(this)">🧑‍💼 تواصل مع الدعم</button>
  </div>

  <div class="ai-input-bar">
    <input type="text" id="aiInput" placeholder="اكتب سؤالك هون..." onkeydown="if(event.key==='Enter')sendAi()">
    <button class="btn" id="aiSend" onclick="sendAi()">إرسال</button>
  </div>
</div>

<script>
const STORE_NAME = <?= json_encode(STORE_NAME) ?>;
const IS_LOGGED = <?= $U ? 'true' : 'false' ?>;
let aiHistory = [];

function esc(s){ return String(s).replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function addMsg(text, who) {
  const box = document.getElementById('aiMessages');
  const div = document.createElement('div');
  div.className = 'ai-msg ' + who;
  div.innerHTML = '<div class="ai-bubble">' + text + '</div>';
  box.appendChild(div);
  box.scrollTop = box.scrollHeight;
  return div;
}

function askSuggestion(btn) {
  document.getElementById('aiInput').value = btn.textContent.replace(/^🧑‍💼\s*/, '');
  sendAi();
}

async function sendAi() {
  const input = document.getElementById('aiInput');
  const btn = document.getElementById('aiSend');
  const sugg = document.getElementById('aiSuggestions');
  const q = input.value.trim();
  if (!q) return;
  if (sugg) sugg.style.display = 'none';
  input.value = '';

  addMsg(esc(q), 'user');
  btn.disabled = true;
  const loading = addMsg('<span class="ai-typing">يكتب<span>.</span><span>.</span><span>.</span></span>', 'bot');

  try {
    const res = await fetch('/ai_chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: q, history: aiHistory }),
    });
    const d = await res.json();
    loading.remove();
    if (d.ok) {
      let html = esc(d.reply).replace(/\n/g, '<br>');
      // إذا في تحويل للدعم: نضيف زر رابط لصفحة الدعم (المساعد يضل يشتغل عادي)
      if (d.support_link) {
        html += '<br><a class="support-link-btn" href="' + d.support_link + '">🧑‍💼 افتح محادثة الدعم</a>';
      }
      addMsg(html, 'bot');
      aiHistory.push({ role: 'user', content: q });
      aiHistory.push({ role: 'assistant', content: d.reply });
      if (aiHistory.length > 12) aiHistory = aiHistory.slice(-12);
    } else {
      addMsg(d.msg || 'صار خطأ، حاول مرة ثانية.', 'bot');
    }
  } catch (e) {
    loading.remove();
    addMsg('تعذّر الاتصال — حاول مرة ثانية.', 'bot');
  }
  btn.disabled = false;
  input.focus();
}
</script>

<?php include __DIR__ . '/footer.php';
