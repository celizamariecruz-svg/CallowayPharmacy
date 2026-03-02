<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <img src="logo-removebg-preview.png" alt="Calloway" width="20" height="20" loading="lazy" style="border-radius:4px; opacity:0.8;">
      <span>Calloway Pharmacy</span>
    </div>
    <div class="footer-copyright">
      &copy; <?php echo date('Y'); ?> Calloway Pharmacy &middot; All rights reserved
    </div>
  </div>
</footer>
<style>
.site-footer {
  width: 100%;
  padding: 1rem 1.5rem;
  background: var(--c-surface, var(--card-bg, #fff));
  border-top: 1px solid var(--c-border, var(--divider-color, #e2e8f0));
  margin-top: auto;
  font-size: 0.8rem;
  color: var(--c-text-muted, var(--text-light, #94a3b8));
}
.footer-inner {
  max-width: 1400px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.footer-brand {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-weight: 600;
  color: var(--c-text-secondary, var(--text-light, #64748b));
}
@media (max-width: 640px) {
  .footer-inner {
    flex-direction: column;
    text-align: center;
  }
}
</style>
