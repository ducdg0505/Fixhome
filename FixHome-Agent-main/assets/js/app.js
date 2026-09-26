(() => {
  document.documentElement.classList.add('js-ready');

  const menuBtn = document.querySelector('[data-menu]');
  const nav = document.querySelector('[data-nav]');
  const closeMenu = () => {
    if (!menuBtn || !nav) return;
    nav.classList.remove('open');
    menuBtn.setAttribute('aria-expanded', 'false');
    menuBtn.setAttribute('aria-label', 'Mở menu');
  };
  if (menuBtn && nav) {
    menuBtn.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      menuBtn.setAttribute('aria-label', open ? 'Đóng menu' : 'Mở menu');
    });
    nav.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
  }

  const setupTabs = (tabs, panels, tabKey, panelKey) => {
    if (!tabs.length || !panels.length) return;
    const activate = (tab, focus = false) => {
      const target = tab.dataset[tabKey];
      tabs.forEach(item => {
        const active = item === tab;
        item.classList.toggle('active', active);
        item.setAttribute('aria-selected', active ? 'true' : 'false');
        item.tabIndex = active ? 0 : -1;
      });
      panels.forEach(panel => {
        const active = panel.dataset[panelKey] === target;
        panel.hidden = !active;
        panel.classList.toggle('panel-enter', active);
      });
      if (focus) tab.focus();
    };
    tabs.forEach((tab, index) => {
      tab.addEventListener('click', () => activate(tab));
      tab.addEventListener('keydown', event => {
        let next = null;
        if (event.key === 'ArrowRight') next = tabs[(index + 1) % tabs.length];
        if (event.key === 'ArrowLeft') next = tabs[(index - 1 + tabs.length) % tabs.length];
        if (event.key === 'Home') next = tabs[0];
        if (event.key === 'End') next = tabs[tabs.length - 1];
        if (!next) return;
        event.preventDefault();
        activate(next, true);
      });
    });
    activate(tabs.find(tab => tab.classList.contains('active')) || tabs[0]);
  };

  setupTabs(
    [...document.querySelectorAll('[data-guest-auth-tab]')],
    [...document.querySelectorAll('[data-guest-auth-panel]')],
    'guestAuthTab',
    'guestAuthPanel'
  );

  document.querySelectorAll('[data-password-toggle]').forEach(button => {
    const field = button.closest('.password-field');
    const input = field?.querySelector('input[type="password"], input[type="text"]');
    if (!input) return;
    button.addEventListener('click', () => {
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      button.setAttribute('aria-pressed', showing ? 'false' : 'true');
      button.setAttribute('aria-label', showing ? 'Hiện mật khẩu' : 'Ẩn mật khẩu');
    });
  });

  const fallbackCopy = value => {
    const field = document.createElement('textarea');
    field.value = value;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();
    field.setSelectionRange(0, value.length);
    const copied = document.execCommand('copy');
    field.remove();
    if (!copied) throw new Error('copy failed');
  };

  document.querySelectorAll('[data-copy-phone]').forEach(button => {
    button.addEventListener('click', async () => {
      const phone = button.dataset.phone || '';
      const statusId = button.getAttribute('aria-describedby');
      const feedback = statusId ? document.getElementById(statusId) : null;
      if (!phone || !feedback) return;
      try {
        if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(phone);
        else fallbackCopy(phone);
        feedback.textContent = 'Đã sao chép số điện thoại';
      } catch (error) {
        feedback.textContent = 'Không thể sao chép tự động. Vui lòng chọn số điện thoại để sao chép.';
      }
      window.setTimeout(() => { feedback.textContent = ''; }, 3000);
    });
  });
  setupTabs(
    [...document.querySelectorAll('[data-home-price-category]')],
    [...document.querySelectorAll('[data-home-price-panel]')],
    'homePriceCategory',
    'homePricePanel'
  );

  const modes = [...document.querySelectorAll('[data-mode]')];
  const popular = document.querySelector('[data-popular]');
  const unknown = document.querySelector('[data-unknown]');
  const unknownPreview = document.querySelector('[data-unknown-preview]');
  const updateMode = () => {
    const selected = document.querySelector('[data-mode]:checked');
    if (!selected || !popular || !unknown) return;
    const isUnknown = selected.value === 'unknown';
    popular.hidden = isUnknown;
    unknown.hidden = !isUnknown;
    if (unknownPreview) unknownPreview.hidden = !isUnknown;
  };
  modes.forEach(radio => radio.addEventListener('change', updateMode));
  updateMode();

  const savedAddressButtons = [...document.querySelectorAll('[data-saved-address]')];
  const bookingAddress = document.querySelector('[data-booking-address]');
  let appliedAddressButton = null;
  savedAddressButtons.forEach(button => {
    button.addEventListener('click', () => {
      if (!bookingAddress) return;
      bookingAddress.value = button.dataset.address || '';
      savedAddressButtons.forEach(option => option.classList.toggle('is-applied', option === button));
      appliedAddressButton = button;
      bookingAddress.focus();
    });
  });
  bookingAddress?.addEventListener('input', () => {
    if (!appliedAddressButton || bookingAddress.value === (appliedAddressButton.dataset.address || '')) return;
    appliedAddressButton.classList.remove('is-applied');
    appliedAddressButton = null;
  });

  const categoryButtons = [...document.querySelectorAll('[data-category-button]')];
  const serviceCards = [...document.querySelectorAll('[data-service-card]')];
  const checks = [...document.querySelectorAll('.service-check input[type="checkbox"]')];
  const estimate = document.querySelector('[data-estimate]');
  const money = value => new Intl.NumberFormat('vi-VN').format(value) + 'đ';
  const updateEstimate = changed => {
    if (changed?.checked) {
      const category = changed.dataset.category;
      checks.forEach(check => {
        if (check !== changed && check.checked && check.dataset.category !== category) check.checked = false;
      });
    }
    const selected = checks.filter(check => check.checked);
    const min = selected.reduce((sum, check) => sum + Number(check.dataset.min || 0), 0);
    const max = selected.reduce((sum, check) => sum + Number(check.dataset.max || 0), 0);
    if (!estimate) return;
    const summary = document.createElement('b');
    const detail = document.createElement('span');
    if (!selected.length) {
      summary.textContent = 'Chưa chọn dịch vụ.';
      detail.textContent = 'Chọn một hoặc nhiều hạng mục trong cùng nhóm.';
      estimate.replaceChildren(summary, detail);
      return;
    }
    const names = selected.map(check => check.dataset.serviceName).filter(Boolean).join(', ');
    summary.textContent = `${selected.length} dịch vụ · ${money(min)} – ${money(max)}`;
    detail.textContent = names;
    estimate.replaceChildren(summary, detail);
  };
  const activateCategory = categoryId => {
    if (!categoryButtons.length || !serviceCards.length) return;
    categoryButtons.forEach(button => {
      const active = button.dataset.categoryButton === String(categoryId);
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    serviceCards.forEach(card => { card.hidden = card.dataset.categoryId !== String(categoryId); });
    checks.forEach(check => {
      if (check.dataset.category !== String(categoryId)) check.checked = false;
    });
    updateEstimate();
  };
  if (categoryButtons.length && serviceCards.length) {
    categoryButtons.forEach(button => button.addEventListener('click', () => activateCategory(button.dataset.categoryButton)));
    activateCategory(categoryButtons.find(button => button.classList.contains('active'))?.dataset.categoryButton || categoryButtons[0].dataset.categoryButton);
  }
  checks.forEach(check => check.addEventListener('change', () => {
    if (check.checked && check.dataset.category) activateCategory(check.dataset.category);
    updateEstimate(check);
  }));
  updateEstimate();

  const orderFilter = document.querySelector('[data-order-filter]');
  const orderCards = [...document.querySelectorAll('[data-order-card]')];
  const noResults = document.querySelector('[data-order-no-results]');
  if (orderFilter && orderCards.length && orderFilter.dataset.clientFilter === 'true') {
    const search = orderFilter.querySelector('[data-order-search]');
    const status = orderFilter.querySelector('[data-order-status-filter]');
    const result = orderFilter.querySelector('[data-order-result]');
    const applyFilter = () => {
      const keyword = (search?.value || '').trim().toLocaleLowerCase('vi');
      const group = status?.value || 'all';
      let visible = 0;
      orderCards.forEach(card => {
        const cardStatus = card.dataset.status || '';
        const matchesKeyword = !keyword || (card.dataset.search || '').toLocaleLowerCase('vi').includes(keyword);
        let matchesGroup = true;
        if (group === 'active') matchesGroup = card.dataset.active === '1';
        else if (group === 'quoted') matchesGroup = ['quoted', 'quote_accepted'].includes(cardStatus);
        else if (group === 'completed') matchesGroup = cardStatus === 'completed';
        else if (group === 'cancelled') matchesGroup = cardStatus === 'cancelled';
        card.hidden = !(matchesKeyword && matchesGroup);
        if (!card.hidden) visible++;
      });
      if (result) result.textContent = `${visible} đơn`;
      if (noResults) noResults.hidden = visible !== 0;
    };
    search?.addEventListener('input', applyFilter);
    status?.addEventListener('change', applyFilter);
    applyFilter();
  }

  let dialogOpener = null;
  const openSensitiveDialog = (dialog, opener) => {
    if (!dialog || typeof dialog.showModal !== 'function') return;
    dialogOpener = opener;
    document.body.classList.add('modal-open');
    dialog.showModal();
    window.setTimeout(() => dialog.querySelector('[data-dialog-password]')?.focus(), 0);
  };

  document.querySelectorAll('[data-dialog-open]').forEach(button => {
    button.addEventListener('click', () => {
      openSensitiveDialog(document.getElementById(button.dataset.dialogOpen || ''), button);
    });
  });

  const adminDialog = document.querySelector('[data-admin-account-dialog]');
  const adminDialogForm = adminDialog?.querySelector('[data-admin-account-form]');
  document.querySelectorAll('[data-account-action-open]').forEach(button => {
    button.addEventListener('click', () => {
      if (!adminDialog || !adminDialogForm) return;
      const actionValue = adminDialogForm.querySelector('[data-dialog-action-value]');
      const reasonField = adminDialogForm.querySelector('[data-dialog-reason-field]');
      const reasonInput = reasonField?.querySelector('textarea');
      adminDialogForm.action = button.dataset.actionUrl || '/admin/accounts';
      adminDialogForm.querySelector('[data-dialog-target-id]').value = button.dataset.targetId || '';
      adminDialogForm.querySelector('[data-dialog-title]').textContent = button.dataset.title || 'Xác nhận thao tác';
      adminDialogForm.querySelector('[data-dialog-description]').textContent = button.dataset.description || '';
      adminDialogForm.querySelector('[data-dialog-target]').textContent = button.dataset.target || '';
      adminDialogForm.querySelector('[data-dialog-confirm]').textContent = button.dataset.confirmLabel || 'Xác nhận';
      if (actionValue) {
        const fieldName = button.dataset.fieldName || '';
        actionValue.disabled = fieldName === '';
        actionValue.name = fieldName;
        actionValue.value = button.dataset.fieldValue || '';
      }
      const needsReason = button.dataset.requiresReason === 'true';
      if (reasonField && reasonInput) {
        reasonField.hidden = !needsReason;
        reasonInput.disabled = !needsReason;
        reasonInput.required = needsReason;
      }
      openSensitiveDialog(adminDialog, button);
    });
  });

  document.querySelectorAll('dialog.sensitive-dialog').forEach(dialog => {
    dialog.querySelectorAll('[data-dialog-cancel]').forEach(button => {
      button.addEventListener('click', () => dialog.close());
    });
    dialog.addEventListener('cancel', event => {
      event.preventDefault();
      dialog.close();
    });
    dialog.addEventListener('close', () => {
      dialog.querySelector('form')?.reset();
      if (!document.querySelector('dialog.sensitive-dialog[open]')) document.body.classList.remove('modal-open');
      if (dialogOpener?.isConnected) dialogOpener.focus();
      dialogOpener = null;
    });
  });

  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', event => {
      const submitter = event.submitter;
      if (!submitter || submitter.dataset.allowRepeat === 'true') return;
      window.setTimeout(() => { submitter.disabled = true; submitter.setAttribute('aria-busy', 'true'); }, 0);
    });
  });

  document.querySelectorAll('[data-disclosure-cancel]').forEach(button => {
    button.addEventListener('click', event => {
      event.preventDefault();
      const form = button.closest('form');
      const disclosure = button.closest('details');
      const summary = disclosure?.querySelector('summary');
      form?.reset();
      if (disclosure) disclosure.open = false;
      summary?.focus();
    });
  });

  const revealItems = [...document.querySelectorAll('[data-reveal]')];
  if (revealItems.length) {
    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        });
      }, { threshold: 0.08, rootMargin: '0px 0px -35px 0px' });
      revealItems.forEach(item => observer.observe(item));
    } else {
      revealItems.forEach(item => item.classList.add('is-visible'));
    }
  }
})();
