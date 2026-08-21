<?php
/*
 Simple Pharmacy POS welcome landing page
 Requirements:
 1) Show first on website
 2) No login required
 3) Enter System -> pages/dashboard.php
 4) Keep existing functionality unchanged
*/
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pharmacy POS Welcome</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #0e5e55;
      --secondary: #095145;
      --card-bg: rgba(255, 255, 255, 0.82);
      --text: #134641;
    }
    body {
      overflow-x: hidden;
      background: linear-gradient(135deg, #dffdf8 0%, #d4eef4 40%, #c7e2f9 100%);
      background-size: 600% 600%;
      animation: gradient-shift 16s ease infinite;
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
      color: var(--text);
      margin: 0;
      min-height: 100vh;
      position: relative;
    }
    @keyframes gradient-shift {
      0% { background-position: 0% 52%; }
      50% { background-position: 100% 49%; }
      100% { background-position: 0% 52%; }
    }
    .hero {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      position: relative;
      z-index: 10;
    }
    .floating-dot {
      position: absolute;
      border-radius: 50%;
      pointer-events: none;
      opacity: 0.32;
      animation: float-up 10s ease-in-out infinite;
      box-shadow: 0 0 18px rgba(14, 94, 85, 0.45);
    }
    .floating-dot.dot1 { width: 26px; height: 26px; background: rgba(18, 101, 92, 0.30); bottom: 16%; left: 12%; animation-delay: 0s; }
    .floating-dot.dot2 { width: 18px; height: 18px; background: rgba(255, 255, 255, 0.55); bottom: 10%; right: 18%; animation-duration: 12s; animation-delay: 2s; }
    .floating-dot.dot3 { width: 14px; height: 14px; background: rgba(16, 123, 112, 0.40); top: 20%; right: 22%; animation-duration: 9s; animation-delay: 1.3s; }
    @keyframes float-up {
      0% { transform: translateY(0) scale(1); }
      50% { transform: translateY(-16px) scale(1.06); }
      100% { transform: translateY(0) scale(1); }
    }
    .card {
      transition: transform 0.35s ease, box-shadow 0.35s ease;
      border-radius: 18px;
      background: var(--card-bg);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      box-shadow: 0 20px 45px rgba(10, 50, 53, 0.18);
      max-width: 560px;
      width: 100%;
      border: 1px solid rgba(15, 144, 127, 0.15);
    }
    .card:hover { transform: translateY(-4px); box-shadow: 0 24px 55px rgba(8, 84, 75, 0.28); }
    .card-body {
      padding: 2rem 2rem 2.5rem;
    }
    h1 {
      letter-spacing: 0.04em;
    }
    .description {
      font-size: 1.05rem;
      margin-bottom: 1.75rem;
      color: #2f6f69;
    }
    .btn-enter {
      border-radius: 999px;
      padding: 0.96rem 1.9rem;
      font-weight: 700;
      font-size: 1rem;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      box-shadow: 0 9px 20px rgba(8, 82, 74, 0.25);
      animation: heartbeat 2.8s infinite;
    }
    @keyframes heartbeat {
      0%, 80%, 100% { transform: scale(1); }
      40% { transform: scale(1.03); }
    }
    .btn-enter:hover {
      transform: translateY(-2px) scale(1.01);
      box-shadow: 0 14px 28px rgba(8, 82, 74, 0.30);
      animation-play-state: paused;
    }
    .brand-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: linear-gradient(145deg, #1ba98b, #0f5c53);
      color: #fff;
      font-size: 1.25rem;
      box-shadow: 0 8px 20px rgba(8, 86, 75, 0.3);
      margin-bottom: 0.75rem;
    }
    .brand-title-wrapper {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.9rem;
      margin-bottom: 0.7rem;
    }
    .brand-text {
      text-transform: uppercase;
      font-size: 0.9rem;
      letter-spacing: 0.1em;
      color: #0f4f49;
      font-weight: 700;
    }
    .feature-list {
      max-width: 320px;
      font-size: 0.95rem;
      color: #1a5a51;
      list-style: none;
      padding-left: 0;
      margin-bottom: 1.2rem;
    }
    .feature-list li {
      position: relative;
      padding-left: 1.5rem;
      margin-bottom: 0.45rem;
      text-align: left;
    }
    .feature-list li::before {
      content: '➤';
      position: absolute;
      left: 0;
      top: 0;
      color: #0f7a6f;
      font-weight: 700;
    }
    .tagline {
      color: #0f4f49;
      font-weight: 600;
      margin-bottom: 0.65rem;
      font-size: 0.94rem;
      text-transform: uppercase;
      letter-spacing: 0.1em;
    }
  </style>
</head>
<body>
  <section class="hero">
    <div class="card">
      <div class="card-body text-center p-5">
        
        <h1 class="mb-3" style="font-weight: 800; color: #0e5e55;">Pharmacy POS</h1>
        <p class="text-secondary mb-3" style="font-size: 1.05rem;">ระบบจัดการร้านยาที่ทันสมัย ครบทุกการขายในที่เดียว</p>
        
        <p class="description">จัดการออเดอร์ ตรวจสอบสต็อก และดูแลลูกค้าได้อย่างง่ายดาย</p>
        <a href="pages/dashboard.php" class="btn btn-success btn-enter">💊 เข้าสู่ระบบ 💊</a>
      </div>
    </div>
  </section>
</body>
</html>
