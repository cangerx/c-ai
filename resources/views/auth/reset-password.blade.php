<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重置密码 - CANG-AI</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:"Noto Sans SC",-apple-system,BlinkMacSystemFont,sans-serif;background:#f5f3f0;color:#1a1a1a;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;-webkit-font-smoothing:antialiased}
        .card{background:rgba(255,255,255,0.92);backdrop-filter:saturate(1.8) blur(20px);border:1px solid rgba(0,0,0,0.06);border-radius:20px;padding:48px 36px;box-shadow:0 24px 48px -12px rgba(0,0,0,0.12);max-width:420px;width:100%}
        h1{font-size:20px;font-weight:700;margin-bottom:8px;text-align:center}
        .desc{color:#6b7280;font-size:14px;margin-bottom:24px;text-align:center}
        label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#374151}
        input{width:100%;padding:12px 14px;border:1.5px solid rgba(0,0,0,0.1);border-radius:10px;font-size:14px;margin-bottom:16px;outline:none;transition:border-color 0.2s}
        input:focus{border-color:#2d5bf0}
        .btn{width:100%;padding:14px;background:#2d5bf0;color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;box-shadow:0 4px 16px rgba(45,91,240,0.25)}
        .btn:disabled{opacity:0.6;cursor:not-allowed}
        .msg{text-align:center;margin-top:16px;font-size:14px;padding:10px;border-radius:8px}
        .msg.ok{background:#ecfdf5;color:#065f46}
        .msg.err{background:#fef2f2;color:#991b1b}
        .back{display:block;text-align:center;margin-top:16px;color:#6b7280;font-size:13px;text-decoration:none}
    </style>
</head>
<body>
<div class="card">
    <h1>重置密码</h1>
    <p class="desc">请输入新密码</p>
    <form id="resetForm">
        <label>新密码</label>
        <input type="password" id="password" minlength="6" required placeholder="至少6位">
        <label>确认密码</label>
        <input type="password" id="password_confirmation" minlength="6" required placeholder="再次输入新密码">
        <button type="submit" class="btn" id="submitBtn">确认重置</button>
    </form>
    <div id="msg" class="msg" style="display:none"></div>
    <a href="/" class="back">← 返回首页</a>
</div>
<script>
const params = new URLSearchParams(location.search);
const token = params.get('token'), email = params.get('email');
if (!token || !email) {
    document.getElementById('resetForm').style.display = 'none';
    const m = document.getElementById('msg');
    m.style.display = 'block';
    m.className = 'msg err';
    m.textContent = '链接无效，请从邮件中重新打开';
}
document.getElementById('resetForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const pw = document.getElementById('password').value;
    const pw2 = document.getElementById('password_confirmation').value;
    const msg = document.getElementById('msg');
    const btn = document.getElementById('submitBtn');
    if (pw !== pw2) { msg.style.display='block'; msg.className='msg err'; msg.textContent='两次密码不一致'; return; }
    btn.disabled = true;
    try {
        const res = await fetch('/api/reset-password', {
            method: 'POST',
            headers: {'Content-Type':'application/json','Accept':'application/json'},
            body: JSON.stringify({token, email, password: pw, password_confirmation: pw2})
        });
        const data = await res.json();
        msg.style.display = 'block';
        msg.className = res.ok ? 'msg ok' : 'msg err';
        msg.textContent = data.message;
        if (res.ok) document.getElementById('resetForm').style.display = 'none';
    } catch { msg.style.display='block'; msg.className='msg err'; msg.textContent='网络错误，请重试'; }
    btn.disabled = false;
});
</script>
</body>
</html>
