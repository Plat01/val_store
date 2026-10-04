// Нативные select WooCommerce остаются источником состояния вариации.
(function () {
  document.querySelectorAll('.variations select').forEach(function (select) {
    if (select.options.length > 18) return;
    const group = document.createElement('div');
    group.className = 'ss-variation-options';
    group.setAttribute('role', 'group');
    group.setAttribute('aria-label', select.closest('tr').querySelector('label')?.textContent || 'Модель');
    const original = Array.from(select.options).filter(o => o.value).map(o => ({ value: o.value, label: o.textContent }));
    original.forEach(function (option) {
      const button = document.createElement('button');
      button.type = 'button'; button.textContent = option.label;
      button.dataset.value = option.value; button.setAttribute('aria-pressed', 'false');
      button.addEventListener('click', function () {
        select.value = option.value;
        // WooCommerce слушает jQuery change и сам проверяет наличие/цену.
        if (window.jQuery) window.jQuery(select).trigger('change');
        else select.dispatchEvent(new Event('change', { bubbles: true }));
        sync();
      });
      group.append(button);
    });
    select.after(group);
    select.parentElement.classList.add('ss-variation-enhanced');
    function sync() {
      group.querySelectorAll('button').forEach(function (button) {
        const option = Array.from(select.options).find(o => o.value === button.dataset.value);
        button.disabled = !option || option.disabled;
        button.setAttribute('aria-pressed', String(select.value === button.dataset.value));
      });
    }
    select.addEventListener('change', sync);
    if (window.jQuery) window.jQuery(select.closest('form')).on('woocommerce_update_variation_values reset_data found_variation', sync);
    sync();
  });
  // Закрытие каталога клавишей Escape и кликом вне меню.
  document.querySelectorAll('.ss-catalog-menu').forEach(function (menu) {
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && menu.open) { menu.open = false; menu.querySelector('summary').focus(); } });
    document.addEventListener('click', function(e) { if (!menu.contains(e.target)) menu.open = false; });
  });
})();
// Сортировка работает без загрузки общего jQuery-скрипта WooCommerce.
document.querySelectorAll('.woocommerce-ordering select.orderby').forEach(function (select) {
  select.addEventListener('change', function () { select.form.requestSubmit(); });
});
