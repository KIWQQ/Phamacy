
<script>
document.addEventListener('DOMContentLoaded', function(){
  const btn = document.getElementById('topSearchBtn');
  const input = document.getElementById('topSearch');
  if (btn && input) btn.addEventListener('click', ()=>{
    const q = input.value.trim();
    if (q) window.location.href = '/Final_Project/pages/order_list.php?search=' + encodeURIComponent(q);
  });

  const themeToggle = document.getElementById('themeToggle');
  const themeIcon = document.getElementById('themeIcon');
  if (themeToggle) {
    themeToggle.addEventListener('click', ()=>{
      document.documentElement.classList.toggle('dark-mode');
      const dark = document.documentElement.classList.contains('dark-mode');
      themeIcon.className = dark ? 'bi bi-moon' : 'bi bi-sun';
      try { localStorage.setItem('phx_theme_dark', dark ? '1' : '0'); } catch(e){}
    });
    try{ if (localStorage.getItem('phx_theme_dark') === '1') { document.documentElement.classList.add('dark-mode'); themeIcon.className='bi bi-moon'; } }catch(e){}
  }

  // sidebar toggle for small screens
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('appSidebar');
  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', ()=>{
      sidebar.classList.toggle('d-none');
    });
  }
});
</script>
