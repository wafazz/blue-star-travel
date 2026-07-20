<style>
:root{
  --blue:#1466ff; --blue-2:#0b3fd1; --sky:#38bdf8; --ink:#0d1b3e; --muted:#7a86a8;
  --bg:#eef2fb; --card:#ffffff; --line:#eef1f8; --ok:#16b364; --danger:#f04438;
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{height:100%}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#dfe6f2;color:var(--ink)}

.app{width:100%;max-width:480px;min-height:100vh;min-height:100dvh;margin:0 auto;background:var(--bg);
  position:relative;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 0 60px rgba(20,50,110,.12)}

.hero{background:linear-gradient(160deg,#1466ff 0%,#0b3fd1 60%,#082aa0 100%);color:#fff;
  padding:60px 28px 90px;border-radius:0 0 40px 40px;position:relative;overflow:hidden;text-align:center}
.hero::after{content:"";position:absolute;right:-50px;top:-50px;width:200px;height:200px;border-radius:50%;
  background:radial-gradient(circle,rgba(56,189,248,.4),transparent 70%)}
.hero::before{content:"";position:absolute;left:-40px;bottom:20px;width:150px;height:150px;border-radius:50%;
  background:radial-gradient(circle,rgba(255,255,255,.12),transparent 70%)}
.logo{width:172px;padding:14px 16px;border-radius:24px;background:#fff;border:1.5px solid rgba(255,255,255,.5);
  display:flex;align-items:center;justify-content:center;margin:0 auto 14px;position:relative;z-index:2;
  box-shadow:0 12px 30px rgba(8,42,160,.28)}
.logo img{width:100%;height:auto;display:block}
.hero h1{font-size:24px;font-weight:800;letter-spacing:-.5px;position:relative;z-index:2}
.hero p{font-size:13px;opacity:.85;margin-top:5px;font-weight:600;position:relative;z-index:2}
.hero .badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.15);padding:6px 13px;
  border-radius:20px;font-size:12px;font-weight:700;margin-top:16px;position:relative;z-index:2}

.card{background:var(--card);margin:-56px 20px 0;border-radius:24px;padding:24px 22px;box-shadow:0 20px 45px rgba(16,42,110,.14);
  position:relative;z-index:5}
.card h2{font-size:19px;font-weight:800}
.card .sub{font-size:13px;color:var(--muted);font-weight:600;margin-top:3px;margin-bottom:20px}

.field{margin-bottom:16px}
.field label{font-size:12.5px;font-weight:700;color:#3a4668;margin-bottom:7px;display:block}
.inp{display:flex;align-items:center;gap:10px;background:#f5f7fc;border:1.5px solid #e7ecf7;border-radius:14px;
  padding:0 14px;transition:.18s}
.inp:focus-within{border-color:var(--blue);background:#fff;box-shadow:0 0 0 4px rgba(20,102,255,.1)}
.inp .ic{font-size:17px;opacity:.7}
.inp input{flex:1;border:none;background:none;outline:none;padding:14px 0;font-size:14px;font-weight:600;color:var(--ink)}
.inp input::placeholder{color:#a9b2cb;font-weight:500}
.inp .eye{cursor:pointer;font-size:16px;opacity:.6;user-select:none}

.row{display:flex;align-items:center;justify-content:space-between;margin:2px 0 20px}
.remember{display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:600;color:#3a4668;cursor:pointer;user-select:none}
.remember .box{width:20px;height:20px;border-radius:7px;border:2px solid #d7deee;display:flex;align-items:center;
  justify-content:center;font-size:12px;color:#fff;transition:.18s}
.remember.on .box{background:var(--blue);border-color:var(--blue)}
.forgot{font-size:12.5px;font-weight:700;color:var(--blue);text-decoration:none;cursor:pointer}

.btn{width:100%;background:linear-gradient(135deg,#1466ff,#0b3fd1);color:#fff;border:none;padding:15px;border-radius:15px;
  font-size:15px;font-weight:800;cursor:pointer;transition:.15s;box-shadow:0 12px 26px rgba(20,102,255,.32);
  display:flex;align-items:center;justify-content:center;gap:8px}
.btn:active{transform:scale(.98)}
.btn.loading{opacity:.8;pointer-events:none}
.spin{width:17px;height:17px;border:2.5px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;
  animation:sp .7s linear infinite;display:none}
.btn.loading .spin{display:block}
.btn.loading .lbl{display:none}
@keyframes sp{to{transform:rotate(360deg)}}

.err{background:#ffe8e6;border:1px solid #ffc9c4;color:#c9302c;font-size:12.5px;font-weight:700;padding:10px 13px;
  border-radius:12px;margin-bottom:16px;display:flex;align-items:center;gap:8px}

.divider{display:flex;align-items:center;gap:12px;margin:22px 0;color:#b3bcd4;font-size:12px;font-weight:600}
.divider::before,.divider::after{content:"";flex:1;height:1px;background:#e7ecf7}

.socials{display:flex;gap:12px}
.soc{flex:1;background:#f5f7fc;border:1.5px solid #e7ecf7;border-radius:14px;padding:12px;font-size:13px;font-weight:700;
  color:#3a4668;display:flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:.15s}
.soc:active{transform:scale(.97);background:#eef2fb}

.foot{text-align:center;font-size:13px;color:var(--muted);font-weight:600;padding:22px 20px 30px}
.foot a{color:var(--blue);font-weight:800;text-decoration:none;cursor:pointer}
.hintbox{background:#eaf1ff;border:1px dashed #b9d0ff;border-radius:12px;padding:10px 13px;font-size:11.5px;
  color:#2a55b8;font-weight:600;margin:0 20px 18px;text-align:center}

.toast{position:fixed;bottom:26px;left:50%;transform:translateX(-50%) translateY(20px);background:#0d1b3e;
  color:#fff;padding:11px 20px;border-radius:14px;font-size:13px;font-weight:700;opacity:0;pointer-events:none;
  transition:.3s;z-index:70;box-shadow:0 10px 30px rgba(0,0,0,.3)}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
</style>
<script>
function togPass(el){
  var i=document.getElementById('pass');
  if(i.type==='password'){i.type='text';el.style.opacity=1;}
  else{i.type='password';el.style.opacity=.6;}
}
function togRemember(){
  var r=document.getElementById('remember');
  r.classList.toggle('on');
  document.getElementById('rememberInput').checked=r.classList.contains('on');
}
function doLogin(){
  document.getElementById('loginBtn').classList.add('loading');
  return true;
}
var tt;
function toast(msg){
  var t=document.getElementById('toast');
  t.textContent=msg;t.classList.add('show');
  clearTimeout(tt);tt=setTimeout(function(){t.classList.remove('show');},1700);
}
</script>
