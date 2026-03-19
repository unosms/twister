(() => {
  const tokenMeta = document.querySelector('meta[name="csrf-token"]');
  const token = tokenMeta ? tokenMeta.getAttribute('content') : '';
  const baseMeta = document.querySelector('meta[name="app-base"]');
  const baseUrl = baseMeta ? baseMeta.getAttribute('content') : '';
  const appBase = (() => {
    if (!baseUrl) {
      return '';
    }
    try {
      const resolved = new URL(baseUrl, window.location.origin);
      if (resolved.origin !== window.location.origin) {
        return '';
      }
      const normalized = resolved.pathname.replace(/\/+$/, '');
      return normalized && normalized !== '/' ? `${resolved.origin}${normalized}` : resolved.origin;
    } catch (error) {
      return '';
    }
  })();
  const endpoint = appBase ? `${appBase}/actions/dispatch` : '/actions/dispatch';
  const liveSearchHiddenClass = 'live-search-hidden';
  const liveSearchStyleId = 'live-search-style';
  const mobileLayoutStyleId = 'mobile-layout-style';
  const mobileSidebarClass = 'sidebar-mobile-open';
  const mobileBreakpointPx = 1023;
  const ciscoModelsWithoutUsername = new Set(['3560', '4948']);

  const normalizeAction = (raw) => {
    if (!raw) {
      return '';
    }
    return raw
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');
  };

  const normalizeServerService = (value) => {
    const normalized = String(value || '')
      .trim()
      .toLowerCase();

    if (!normalized) {
      return '';
    }

    return normalized === 'netplat' ? 'netplay' : normalized;
  };

  const collectSelectedServerServices = ({ select = null, checkboxes = [] } = {}) =>
    Array.from(
      new Set(
        [
          ...(select
            ? Array.from(select.selectedOptions || []).map((option) => normalizeServerService(option.value))
            : []),
          ...checkboxes.map((input) => (input.checked ? normalizeServerService(input.value) : '')),
        ].filter((value) => value !== '')
      )
    );

  const ciscoModelUsesUsername = (model) =>
    !ciscoModelsWithoutUsername.has(String(model || '').trim().toUpperCase());

  const getActionFromElement = (element) => {
    if (!element) {
      return '';
    }
    return (
      element.dataset.action ||
      element.getAttribute('aria-label') ||
      element.getAttribute('title') ||
      element.textContent
    );
  };

  const dispatchAction = async (action, payload = {}) => {
    if (!action) {
      return;
    }

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token,
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          action,
          source: window.location.pathname,
          ...payload,
        }),
      });

      if (!response.ok) {
        console.warn('Action dispatch failed', action, response.status);
        return;
      }

      const data = await response.json().catch(() => ({}));
      console.info('Action dispatched', data);
    } catch (error) {
      console.warn('Action dispatch error', action, error);
    }
  };

  const togglePassword = (button) => {
    const targetSelector = button.dataset.target || '#password';
    const input = document.querySelector(targetSelector);
    if (!input) {
      return false;
    }

    const nextType = input.type === 'password' ? 'text' : 'password';
    input.type = nextType;

    const icon = button.querySelector('.material-symbols-outlined');
    if (icon) {
      icon.textContent = nextType === 'password' ? 'visibility_off' : 'visibility';
    }

    button.setAttribute('aria-pressed', nextType === 'text' ? 'true' : 'false');
    return true;
  };

  const setupLoginFormEnhancements = () => {
    const forms = Array.from(document.querySelectorAll('[data-login-form]'));
    forms.forEach((form) => {
      if (form.dataset.loginFormBound === 'true') {
        return;
      }

      const submitButton = form.querySelector('[data-login-submit]');
      const submitLabel = submitButton ? submitButton.querySelector('[data-login-label]') : null;

      form.addEventListener('submit', () => {
        if (!submitButton || submitButton.dataset.submitting === '1') {
          return;
        }

        submitButton.dataset.submitting = '1';
        submitButton.disabled = true;
        submitButton.classList.add('opacity-80', 'cursor-not-allowed');
        if (submitLabel) {
          submitLabel.textContent = 'Signing in...';
        }
      });

      form.dataset.loginFormBound = 'true';
    });

    const passwordInputs = Array.from(
      document.querySelectorAll('input[type="password"][data-caps-lock-target]')
    );

    passwordInputs.forEach((input) => {
      if (input.dataset.capsLockBound === 'true') {
        return;
      }

      const warningKey = input.dataset.capsLockTarget || '';
      if (!warningKey) {
        return;
      }

      const warning = document.querySelector(`[data-caps-lock-warning="${warningKey}"]`);
      if (!warning) {
        return;
      }

      const updateWarning = (event) => {
        const hasState = event && typeof event.getModifierState === 'function';
        const capsOn = hasState ? event.getModifierState('CapsLock') : false;
        warning.classList.toggle('hidden', !capsOn);
      };

      input.addEventListener('keydown', updateWarning);
      input.addEventListener('keyup', updateWarning);
      input.addEventListener('focus', updateWarning);
      input.addEventListener('blur', () => {
        warning.classList.add('hidden');
      });

      input.dataset.capsLockBound = 'true';
    });
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest('button');
    if (button) {
      if (button.dataset.toggle === 'password') {
        event.preventDefault();
        togglePassword(button);
        return;
      }

      if (
        button.dataset.noDispatch === 'true' ||
        button.hasAttribute('data-modal-open') ||
        button.hasAttribute('data-modal-close') ||
        button.hasAttribute('data-device-filter') ||
        button.hasAttribute('data-portal-notifications') ||
        button.hasAttribute('data-chip-remove') ||
        button.hasAttribute('data-chip-add') ||
        button.hasAttribute('data-copy-target') ||
        button.hasAttribute('data-toast-close')
      ) {
        return;
      }

      const isSubmit = button.type !== 'button';
      if (button.closest('form') && isSubmit) {
        return;
      }

      const actionRaw = getActionFromElement(button);
      const action = normalizeAction(actionRaw);
      if (!action) {
        return;
      }

      const payload = {};
      if (button.dataset.deviceId) {
        payload.device_id = button.dataset.deviceId;
      }
      if (button.dataset.commandId) {
        payload.command_template_id = button.dataset.commandId;
      }

      event.preventDefault();
      dispatchAction(action, payload);
      return;
    }

    const link = event.target.closest('a[href="#"]');
    if (link) {
      const actionRaw = getActionFromElement(link);
      const action = normalizeAction(actionRaw || 'link_action');
      event.preventDefault();
      dispatchAction(action);
    }
  });

  const setupOtpInputs = () => {
    const containers = document.querySelectorAll('[data-otp]');
    if (!containers.length) {
      return;
    }

    containers.forEach((container) => {
      const inputs = Array.from(container.querySelectorAll('input[type=\"number\"]'));
      if (!inputs.length) {
        return;
      }

      inputs.forEach((input, index) => {
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('pattern', '[0-9]*');

        input.addEventListener('input', () => {
          const value = input.value.replace(/\D/g, '');
          input.value = value.slice(0, 1);

          if (input.value && index < inputs.length - 1) {
            inputs[index + 1].focus();
          }
        });

        input.addEventListener('keydown', (event) => {
          if (event.key === 'Backspace' && !input.value && index > 0) {
            inputs[index - 1].focus();
          }
        });

        input.addEventListener('paste', (event) => {
          const paste = (event.clipboardData || window.clipboardData).getData('text');
          if (!paste) {
            return;
          }
          const digits = paste.replace(/\D/g, '').slice(0, inputs.length);
          if (!digits.length) {
            return;
          }
          event.preventDefault();
          digits.split('').forEach((digit, idx) => {
            if (inputs[idx]) {
              inputs[idx].value = digit;
            }
          });
          const nextIndex = Math.min(digits.length, inputs.length - 1);
          inputs[nextIndex].focus();
        });
      });
    });
  };

  const setupCommandSelects = () => {
    const selects = Array.from(document.querySelectorAll('[data-command-select]'));
    if (!selects.length) {
      return;
    }

    selects.forEach((select) => {
      select.addEventListener('change', () => {
        const action = select.value;
        if (!action) {
          return;
        }
        const deviceId = select.dataset.deviceId || '';
        const execBase = appBase ? `${appBase}/exec.php` : '/exec.php';

        const needsInterface = new Set([
          'showmac',
          'showint',
          'restartint',
          'disableint',
          'enableint',
          'shspantree',
          'renameint',
          'shtransceiver',
        ]);
        const needsDescription = new Set(['renameint']);

        let iface = '';
        let desc = '';

        if (needsInterface.has(action)) {
          iface = window.prompt('Enter interface (e.g. Gi1/0/1):', '') || '';
          if (!iface.trim()) {
            select.selectedIndex = 0;
            return;
          }
        }

        if (needsDescription.has(action)) {
          desc = window.prompt('Enter description:', '') || '';
          if (!desc.trim()) {
            select.selectedIndex = 0;
            return;
          }
        }

        const query = new URLSearchParams({ cmd: action });
        if (deviceId) {
          query.set('id', deviceId);
        }
        if (iface) {
          query.set('iface', iface);
        }
        if (desc) {
          query.set('description', desc);
        }

        const execUrl = `${execBase}?${query.toString()}`;
        window.open(execUrl, '_blank', 'noopener');

        const payload = {};
        if (deviceId) {
          payload.device_id = deviceId;
        }
        dispatchAction(action, payload);
        select.selectedIndex = 0;
      });
    });
  };

  const setupModalToggles = () => {
    const openButtons = Array.from(document.querySelectorAll('[data-modal-open]'));
    const closeButtons = Array.from(document.querySelectorAll('[data-modal-close]'));

    const openModal = (id) => {
      if (!id) {
        return;
      }
      const modal = document.getElementById(id);
      if (!modal) {
        return;
      }
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    };

    const closeModal = (id, element) => {
      const modal = id ? document.getElementById(id) : element?.closest('[data-modal]');
      if (!modal) {
        return;
      }
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    };

    openButtons.forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        openModal(button.dataset.modalOpen);
      });
    });

    closeButtons.forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        closeModal(button.dataset.modalClose, button);
      });
    });

    document.querySelectorAll('[data-modal]').forEach((modal) => {
      modal.addEventListener('click', (event) => {
        if (event.target === modal) {
          closeModal(null, modal);
        }
      });
    });
  };

  const setupToastClose = () => {
    document.querySelectorAll('[data-toast-close]').forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        const toast = button.closest('.fixed');
        if (toast) {
          toast.remove();
        }
      });
    });
  };

  const setupCommandSlug = () => {
    const nameInput = document.getElementById('command-name');
    const keyInput = document.getElementById('command-action-key');
    if (!nameInput || !keyInput) {
      return;
    }

    const updateKey = () => {
      keyInput.value = normalizeAction(nameInput.value || '');
    };

    nameInput.addEventListener('input', updateKey);
    if (!keyInput.value) {
      updateKey();
    }
  };

  const setupCopyButtons = () => {
    document.querySelectorAll('[data-copy-target]').forEach((button) => {
      button.addEventListener('click', async (event) => {
        event.preventDefault();
        const targetSelector = button.dataset.copyTarget;
        const target = targetSelector ? document.querySelector(targetSelector) : null;
        if (!target) {
          return;
        }
        const value = target.value || target.textContent || '';
        try {
          await navigator.clipboard.writeText(value);
          button.classList.add('bg-emerald-600');
          setTimeout(() => button.classList.remove('bg-emerald-600'), 800);
        } catch (error) {
          console.warn('Copy failed', error);
        }
      });
    });
  };

  const setupChips = () => {
    document.querySelectorAll('[data-chip-remove]').forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        const chip = button.closest('span, div');
        if (chip) {
          chip.remove();
        }
      });
    });

    document.querySelectorAll('[data-chip-add]').forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        const container = button.closest('div');
        const name = window.prompt('Enter role name');
        if (!name || !container) {
          return;
        }
        const chip = document.createElement('span');
        chip.className = 'inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 dark:bg-slate-800 rounded-full text-xs font-bold text-slate-600 dark:text-slate-200';
        chip.innerHTML = `${name} <button class=\"material-symbols-outlined text-xs\" type=\"button\" data-chip-remove>close</button>`;
        container.insertBefore(chip, button);
        const removeButton = chip.querySelector('[data-chip-remove]');
        if (removeButton) {
          removeButton.addEventListener('click', (evt) => {
            evt.preventDefault();
            chip.remove();
          });
        }
      });
    });
  };

  const ensureLiveSearchStyle = () => {
    if (document.getElementById(liveSearchStyleId)) {
      return;
    }
    const style = document.createElement('style');
    style.id = liveSearchStyleId;
    style.textContent = `.${liveSearchHiddenClass} { display: none !important; }`;
    document.head.appendChild(style);
  };

  const ensureMobileLayoutStyle = () => {
    if (document.getElementById(mobileLayoutStyleId)) {
      return;
    }

    const style = document.createElement('style');
    style.id = mobileLayoutStyleId;
    style.textContent = `
      @media (max-width: ${mobileBreakpointPx}px) {
        body {
          overflow-x: hidden;
        }

        [data-sidebar] {
          position: fixed !important;
          top: 0;
          left: 0;
          bottom: 0;
          z-index: 70;
          width: min(20rem, 85vw) !important;
          max-width: 85vw !important;
          height: 100vh !important;
          transform: translateX(-105%);
          transition: transform 0.2s ease;
          overflow-y: auto;
          box-shadow: 0 18px 40px rgba(15, 23, 42, 0.28);
          display: flex !important;
        }

        body.${mobileSidebarClass} [data-sidebar] {
          transform: translateX(0);
        }

        body.${mobileSidebarClass} {
          overflow: hidden;
        }

        body.${mobileSidebarClass}::before {
          content: '';
          position: fixed;
          inset: 0;
          z-index: 65;
          background: rgba(15, 23, 42, 0.45);
        }

        [data-mobile-table-scroll] {
          overflow-x: auto;
          -webkit-overflow-scrolling: touch;
        }

        [data-mobile-table-scroll] > table {
          min-width: 680px;
        }

        [data-device-layout="split"] {
          flex-direction: column !important;
        }

        [data-device-drawer] {
          width: 100% !important;
          max-width: 100% !important;
          border-left: none !important;
          border-top: 1px solid rgba(148, 163, 184, 0.35);
          max-height: none !important;
        }
      }
    `;

    document.head.appendChild(style);
  };

  const setupMobileTableScroll = () => {
    ensureMobileLayoutStyle();

    const tables = Array.from(document.querySelectorAll('table'));
    if (!tables.length) {
      return;
    }

    tables.forEach((table) => {
      if (table.closest('.overflow-x-auto, [data-mobile-table-scroll]')) {
        return;
      }

      const parent = table.parentElement;
      if (!parent) {
        return;
      }

      const wrapper = document.createElement('div');
      wrapper.setAttribute('data-mobile-table-scroll', 'true');
      parent.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    });
  };

  const setupLiveSearch = () => {
    const inputs = Array.from(document.querySelectorAll('[data-live-search]'));
    if (!inputs.length) {
      return;
    }

    ensureLiveSearchStyle();

    inputs.forEach((input) => {
      if (input.dataset.liveSearchBound === 'true') {
        return;
      }

      const targetSelector = (input.dataset.liveSearchTarget || '').trim();
      if (!targetSelector) {
        return;
      }

      const getTargets = () => {
        try {
          return Array.from(document.querySelectorAll(targetSelector));
        } catch (error) {
          console.warn('Invalid live search selector', targetSelector, error);
          return [];
        }
      };

      const applyFilter = () => {
        const term = (input.value || '').trim().toLowerCase();
        const targets = getTargets();
        targets.forEach((target) => {
          const haystack = (target.dataset.liveSearchText || target.textContent || '').toLowerCase();
          const match = !term || haystack.includes(term);
          target.classList.toggle(liveSearchHiddenClass, !match);
        });
      };

      input.addEventListener('input', applyFilter);
      input.addEventListener('search', applyFilter);
      const form = input.closest('form');
      if (form) {
        form.addEventListener('reset', () => {
          window.requestAnimationFrame(applyFilter);
        });
      }

      input.dataset.liveSearchBound = 'true';
      applyFilter();
    });
  };

  const setupWizardFilters = () => {
    const userSearch = document.querySelector('[data-user-search]');
    const userRows = Array.from(document.querySelectorAll('[data-user-row]'));
    if (userSearch && userRows.length) {
      userSearch.addEventListener('input', () => {
        const term = userSearch.value.trim().toLowerCase();
        userRows.forEach((row) => {
          const name = (row.dataset.userName || '').toLowerCase();
          const email = (row.dataset.userEmail || '').toLowerCase();
          const match = !term || name.includes(term) || email.includes(term);
          row.classList.toggle('hidden', !match);
        });
      });
    }

    const deviceFilter = document.querySelector('[data-device-filter]');
    const deviceSearch = document.querySelector('[data-device-search]');
    const deviceCards = Array.from(document.querySelectorAll('[data-device-card]'));
    if ((deviceFilter || deviceSearch) && deviceCards.length) {
      const applyDeviceFilter = () => {
        const type = (deviceFilter?.value || 'all').toUpperCase();
        const term = (deviceSearch?.value || '').trim().toLowerCase();
        deviceCards.forEach((card) => {
          const cardType = (card.dataset.deviceType || '').toUpperCase();
          const name = (card.dataset.deviceName || '').toLowerCase();
          const serial = (card.dataset.deviceSerial || '').toLowerCase();
          const matchesType = type === 'ALL' || !type || cardType === type;
          const matchesTerm = !term || name.includes(term) || serial.includes(term);
          card.classList.toggle('hidden', !(matchesType && matchesTerm));
        });
      };

      if (deviceFilter) {
        deviceFilter.addEventListener('change', applyDeviceFilter);
      }
      if (deviceSearch) {
        deviceSearch.addEventListener('input', applyDeviceFilter);
      }
    }
  };

  const setupPortalFilters = () => {
    const filterButtons = Array.from(document.querySelectorAll('[data-device-filter]'));
    const cards = Array.from(document.querySelectorAll('[data-device-status]'));
    if (!filterButtons.length || !cards.length) {
      return;
    }

    const visibleCount = document.querySelector('[data-portal-visible-count]');
    const emptyState = document.querySelector('[data-portal-empty]');

    const setButtonState = (button, isActive) => {
      button.classList.toggle('bg-primary', isActive);
      button.classList.toggle('text-white', isActive);
      button.classList.toggle('border-primary', isActive);

      button.classList.toggle('bg-white', !isActive);
      button.classList.toggle('dark:bg-slate-800', !isActive);
      button.classList.toggle('text-slate-700', !isActive);
      button.classList.toggle('dark:text-slate-200', !isActive);
      button.classList.toggle('border-slate-200', !isActive);
      button.classList.toggle('dark:border-slate-700', !isActive);
    };

    const updateSummary = () => {
      let visible = 0;
      cards.forEach((card) => {
        const hiddenByFilter = card.classList.contains('hidden');
        const hiddenBySearch = card.classList.contains(liveSearchHiddenClass);
        if (!hiddenByFilter && !hiddenBySearch) {
          visible += 1;
        }
      });

      if (visibleCount) {
        visibleCount.textContent = String(visible);
      }
      if (emptyState) {
        emptyState.classList.toggle('hidden', visible > 0);
      }
    };

    const applyFilter = (filter) => {
      cards.forEach((card) => {
        const status = (card.dataset.deviceStatus || '').toLowerCase();
        const match = filter === 'all'
          ? true
          : (filter === 'offline'
            ? status !== 'online' && status !== 'warning'
            : status === filter);
        card.classList.toggle('hidden', !match);
      });

      filterButtons.forEach((button) => {
        const isActive = (button.dataset.deviceFilter || 'all').toLowerCase() === filter;
        setButtonState(button, isActive);
      });

      updateSummary();
    };

    filterButtons.forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        const filter = (button.dataset.deviceFilter || 'all').toLowerCase();
        applyFilter(filter);
      });
    });

    const searchInputs = Array.from(document.querySelectorAll('[data-live-search]'));
    searchInputs.forEach((input) => {
      input.addEventListener('input', updateSummary);
      input.addEventListener('search', updateSummary);
    });

    if (typeof MutationObserver === 'function') {
      const observer = new MutationObserver(updateSummary);
      cards.forEach((card) => {
        observer.observe(card, { attributes: true, attributeFilter: ['class'] });
      });
    }

    applyFilter('all');
  };

  const setupPortalNotifications = () => {
    const button = document.querySelector('[data-portal-notifications]');
    const dropdown = document.getElementById('portal-notifications');
    if (!button || !dropdown) {
      return;
    }

    button.addEventListener('click', (event) => {
      event.preventDefault();
      dropdown.classList.toggle('hidden');
    });

    document.addEventListener('click', (event) => {
      if (!dropdown.classList.contains('hidden') && !button.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.classList.add('hidden');
      }
    });
  };

  const escapeHtml = (value) => {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };

  const setupNotificationsMenu = () => {
    const buttons = Array.from(document.querySelectorAll('[data-notifications-menu-button]'));
    if (!buttons.length) {
      return;
    }

    const severityClassMap = {
      critical: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
      warning: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
      info: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
      default: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    };

    const defaultNotificationsUrl = appBase ? `${appBase}/notifications?status=open` : '/notifications?status=open';

    const entries = buttons
      .map((button) => {
        const wrapper = button.parentElement;
        const menu = wrapper ? wrapper.querySelector('[data-notifications-menu]') : null;
        if (!menu) {
          return null;
        }

        return {
          button,
          menu,
          indicator: button.querySelector('[data-notifications-indicator]'),
          summary: menu.querySelector('[data-notifications-menu-summary]'),
          body: menu.querySelector('[data-notifications-menu-body]'),
          endpoint: button.dataset.notificationsEndpoint || (appBase ? `${appBase}/notifications/menu` : '/notifications/menu'),
          loaded: false,
          loading: false,
        };
      })
      .filter(Boolean);

    if (!entries.length) {
      return;
    }

    const renderMenu = (entry, payload) => {
      const openCount = Number(payload && payload.open_count ? payload.open_count : 0);
      const items = Array.isArray(payload && payload.items) ? payload.items : [];

      if (entry.summary) {
        entry.summary.textContent = openCount > 0
          ? `${openCount} open alert${openCount === 1 ? '' : 's'}`
          : 'No open alerts';
      }

      if (entry.indicator) {
        entry.indicator.classList.toggle('hidden', openCount <= 0);
      }

      if (!entry.body) {
        return;
      }

      if (!items.length) {
        entry.body.innerHTML = '<div class="px-4 py-5 text-sm text-slate-500">No notifications right now.</div>';
        return;
      }

      entry.body.innerHTML = items.map((item) => {
        const severity = String(item.severity || 'default').toLowerCase();
        const severityClass = severityClassMap[severity] || severityClassMap.default;
        const deviceName = item.device_name
          ? `<p class="mt-1 text-[11px] text-slate-500">${escapeHtml(item.device_name)}</p>`
          : '';
        const createdAt = item.created_at_human
          ? `<span class="text-[11px] text-slate-400">${escapeHtml(item.created_at_human)}</span>`
          : '';

        return `
          <a class="block px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors" href="${escapeHtml(defaultNotificationsUrl)}">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${severityClass}">${escapeHtml(severity)}</span>
                  ${createdAt}
                </div>
                <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">${escapeHtml(item.title || 'Alert')}</p>
                <p class="mt-1 text-xs text-slate-500">${escapeHtml(item.message || '')}</p>
                ${deviceName}
              </div>
            </div>
          </a>
        `;
      }).join('');
    };

    const loadMenu = async (entry) => {
      if (entry.loading || !entry.body) {
        return;
      }

      entry.loading = true;
      entry.body.innerHTML = '<div class="px-4 py-5 text-sm text-slate-500">Loading notifications...</div>';

      try {
        const response = await fetch(entry.endpoint, {
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        });

        if (!response.ok) {
          throw new Error(`Notifications menu request failed: ${response.status}`);
        }

        const payload = await response.json();
        renderMenu(entry, payload);
        entry.loaded = true;
      } catch (error) {
        if (entry.summary) {
          entry.summary.textContent = 'Could not load notifications';
        }
        if (entry.body) {
          entry.body.innerHTML = '<div class="px-4 py-5 text-sm text-slate-500">Could not load notifications.</div>';
        }
      } finally {
        entry.loading = false;
      }
    };

    const closeAllMenus = (exceptEntry = null) => {
      entries.forEach((entry) => {
        if (entry === exceptEntry) {
          return;
        }
        entry.menu.classList.add('hidden');
        entry.button.setAttribute('aria-expanded', 'false');
      });
    };

    entries.forEach((entry) => {
      entry.button.setAttribute('aria-expanded', 'false');

      entry.button.addEventListener('click', async (event) => {
        event.preventDefault();
        const willOpen = entry.menu.classList.contains('hidden');

        closeAllMenus(willOpen ? entry : null);
        entry.menu.classList.toggle('hidden', !willOpen);
        entry.button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

        if (willOpen) {
          await loadMenu(entry);
        }
      });

      loadMenu(entry);
    });

    document.addEventListener('click', (event) => {
      const clickedInsideMenu = entries.some((entry) => entry.button.contains(event.target) || entry.menu.contains(event.target));
      if (!clickedInsideMenu) {
        closeAllMenus();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeAllMenus();
      }
    });
  };

  const setupDeviceTypeFields = () => {
    const typeSelect = document.querySelector('[data-device-type]');
    const ciscoFields = document.querySelector('[data-cisco-fields]');
    const mimosaFields = document.querySelector('[data-mimosa-fields]');
    const serverFields = document.querySelector('[data-server-fields]');
    const oltFields = document.querySelector('[data-olt-fields]');
    const mikrotikFields = document.querySelector('[data-mikrotik-fields]');
    const serverType = serverFields ? serverFields.querySelector('[data-server-type]') : null;
    const serverServiceSelect = serverFields
      ? serverFields.querySelector('select[data-server-service]')
      : null;
    const serverServiceCheckboxes = serverFields
      ? Array.from(serverFields.querySelectorAll('[data-server-service-option]'))
      : [];
    const serverStandaloneFields = serverFields ? Array.from(serverFields.querySelectorAll('[data-server-standalone-field]')) : [];
    const serverServiceFieldGroups = serverFields ? Array.from(serverFields.querySelectorAll('[data-server-service-fields]')) : [];
    const mimosaModel = document.querySelector('[data-mimosa-model]');
    if (!typeSelect || !ciscoFields) {
      return;
    }

    const requiredFields = Array.from(ciscoFields.querySelectorAll('[data-cisco-required]'));
    const ciscoInputs = Array.from(ciscoFields.querySelectorAll('input, select, textarea'));
    const ciscoUsernameFields = Array.from(ciscoFields.querySelectorAll('[data-cisco-username-field]'));
    const ciscoSwitchModel = ciscoFields.querySelector('[data-cisco-switch-model]');
    const ciscoName = ciscoFields.querySelector('[data-cisco-name]');
    const shbackup = ciscoFields.querySelector('[data-cisco-shbackup]');
    const execCmd = ciscoFields.querySelector('[data-cisco-exec]');
    const folderLocation = ciscoFields.querySelector('[data-cisco-folder]');
    const defaultValues = new Map();
    const mimosaForms = mimosaFields ? Array.from(mimosaFields.querySelectorAll('[data-mimosa-form]')) : [];
    const mimosaInputs = mimosaFields ? Array.from(mimosaFields.querySelectorAll('input, select, textarea')) : [];
    const serverInputs = serverFields ? Array.from(serverFields.querySelectorAll('input, select, textarea')) : [];
    const serverRequired = serverFields ? Array.from(serverFields.querySelectorAll('[data-server-required]')) : [];
    const oltInputs = oltFields ? Array.from(oltFields.querySelectorAll('input, select, textarea')) : [];
    const oltRequired = oltFields ? Array.from(oltFields.querySelectorAll('[data-olt-required]')) : [];
    const oltName = oltFields ? oltFields.querySelector('input[name="name"]') : null;
    const oltFolderLocation = oltFields ? oltFields.querySelector('input[name="olt_folder_location"]') : null;
    const mikrotikInputs = mikrotikFields ? Array.from(mikrotikFields.querySelectorAll('input, select, textarea')) : [];
    const mikrotikRequired = mikrotikFields ? Array.from(mikrotikFields.querySelectorAll('[data-mikrotik-required]')) : [];
    const serverDefaults = new Map();
    const oltDefaults = new Map();
    const mikrotikDefaults = new Map();
    let revealed = false;

    ciscoInputs.forEach((field) => {
      defaultValues.set(field, field.value);
    });
    serverInputs.forEach((field) => {
      if (field.type === 'checkbox' || field.type === 'radio') {
        serverDefaults.set(field, field.checked);
        return;
      }
      if (field.tagName === 'SELECT' && field.multiple) {
        serverDefaults.set(
          field,
          Array.from(field.options || [])
            .filter((option) => option.selected)
            .map((option) => option.value)
        );
        return;
      }
      serverDefaults.set(field, field.value);
    });
    oltInputs.forEach((field) => {
      oltDefaults.set(field, field.value);
    });
    mikrotikInputs.forEach((field) => {
      mikrotikDefaults.set(field, field.value);
    });

    const markManual = (field) => {
      if (field) {
        field.dataset.auto = 'false';
      }
    };

    [shbackup, execCmd, folderLocation].forEach((field) => {
      if (!field) {
        return;
      }
      field.dataset.auto = 'true';
      field.addEventListener('input', () => markManual(field));
    });

    if (oltFolderLocation) {
      oltFolderLocation.dataset.auto = oltFolderLocation.value ? 'false' : 'true';
      oltFolderLocation.addEventListener('input', () => markManual(oltFolderLocation));
    }

    const normalizeFolderName = (value) =>
      String(value || '')
        .trim()
        .replace(/[\\/]+/g, '-')
        .replace(/\s+/g, '_');

    const updateOltFolderLocation = () => {
      if (!oltName || !oltFolderLocation) {
        return;
      }

      const isAuto = oltFolderLocation.dataset.auto !== 'false';
      if (!isAuto) {
        return;
      }

      const normalizedName = normalizeFolderName(oltName.value);
      oltFolderLocation.value = normalizedName ? `uno/${normalizedName}` : '';
      oltFolderLocation.dataset.auto = 'true';
    };

    const updateAutoFields = () => {
      if (!ciscoName) {
        return;
      }
      const nameValue = (ciscoName.value || '').trim();
      const applyAuto = (field, value) => {
        if (!field) {
          return;
        }
        const isAuto = field.dataset.auto === 'true';
        if (field.value && !isAuto) {
          return;
        }
        field.value = value;
        field.dataset.auto = 'true';
      };

      if (!nameValue) {
        [shbackup, execCmd, folderLocation].forEach((field) => {
          if (field && field.dataset.auto === 'true') {
            field.value = '';
          }
        });
        return;
      }

      applyAuto(shbackup, `showbackup.php?name=${nameValue}`);
      applyAuto(execCmd, `exec.php?name=${nameValue}`);
      applyAuto(folderLocation, `uno/${nameValue}`);
    };

    const revealCiscoFields = () => {
      const isCisco = (typeSelect.value || '').toUpperCase() === 'CISCO';
      if (!isCisco) {
        return;
      }
      if (revealed) {
        return;
      }
      revealed = true;
      ciscoFields.classList.remove('hidden');
      ciscoInputs.forEach((field) => {
        field.disabled = false;
      });
      requiredFields.forEach((field) => {
        field.required = true;
      });
      updateAutoFields();
      toggleCiscoUsernameFields();
    };

    const resetCiscoInputs = () => {
      ciscoInputs.forEach((field) => {
        const defaultValue = defaultValues.get(field) ?? '';
        if (field.tagName === 'SELECT') {
          field.value = defaultValue;
        } else {
          field.value = defaultValue;
        }
      });
      [shbackup, execCmd, folderLocation].forEach((field) => {
        if (field) {
          field.dataset.auto = 'true';
        }
      });
    };

    const hideCiscoFields = (reset = false) => {
      revealed = false;
      ciscoFields.classList.add('hidden');
      ciscoInputs.forEach((field) => {
        field.disabled = true;
      });
      requiredFields.forEach((field) => {
        field.required = false;
      });
      if (reset) {
        resetCiscoInputs();
      }
    };

    const toggleCiscoUsernameFields = () => {
      const isCiscoVisible = !ciscoFields.classList.contains('hidden');
      const showUsername = isCiscoVisible && ciscoModelUsesUsername(ciscoSwitchModel?.value || '');

      ciscoUsernameFields.forEach((group) => {
        group.classList.toggle('hidden', !showUsername);
        group.querySelectorAll('input, select, textarea').forEach((field) => {
          if (!showUsername) {
            field.value = '';
          }
          field.disabled = !showUsername;
        });
        group.querySelectorAll('[data-cisco-required]').forEach((field) => {
          field.required = showUsername;
        });
      });
    };

    const showMimosaForm = () => {
      if (!mimosaFields || !mimosaModel) {
        return;
      }
      const model = (mimosaModel.value || 'C5C').toUpperCase();
      mimosaForms.forEach((form) => {
        const isActive = (form.dataset.mimosaForm || '').toUpperCase() === model;
        form.classList.toggle('hidden', !isActive);
        form.querySelectorAll('input, select, textarea').forEach((field) => {
          field.disabled = !isActive;
        });
        form.querySelectorAll('[data-mimosa-required]').forEach((field) => {
          field.required = isActive;
        });
      });
    };

    const hideMimosaFields = () => {
      if (!mimosaFields) {
        return;
      }
      mimosaFields.classList.add('hidden');
      mimosaInputs.forEach((field) => {
        field.disabled = true;
        if (field.hasAttribute('data-mimosa-required')) {
          field.required = false;
        }
      });
      mimosaForms.forEach((form) => {
        form.classList.add('hidden');
        form.querySelectorAll('[data-mimosa-required]').forEach((field) => {
          field.required = false;
        });
      });
      if (mimosaModel) {
        mimosaModel.disabled = true;
      }
    };

    const showMimosaFields = () => {
      if (!mimosaFields) {
        return;
      }
      mimosaFields.classList.remove('hidden');
      mimosaInputs.forEach((field) => {
        if (!field.closest('[data-mimosa-form]')) {
          field.disabled = false;
          if (field.hasAttribute('data-mimosa-required')) {
            field.required = true;
          }
        }
      });
      if (mimosaModel) {
        mimosaModel.disabled = false;
      }
      showMimosaForm();
    };

    const toggleServerStandaloneFields = () => {
      if (!serverFields) {
        return;
      }
      const isServerVisible = !serverFields.classList.contains('hidden');
      const selectedServerType = String(serverType?.value || 'virtual_server').toLowerCase();
      const isStandalone = isServerVisible && selectedServerType === 'stand_alone_server';

      serverStandaloneFields.forEach((group) => {
        group.classList.toggle('hidden', !isStandalone);
        group.querySelectorAll('input, select, textarea').forEach((field) => {
          field.disabled = !isStandalone;
        });
        group.querySelectorAll('[data-server-standalone-required]').forEach((field) => {
          field.required = isStandalone;
        });
      });
    };

    const toggleServerServiceFields = () => {
      if (!serverFields) {
        return;
      }
      const isServerVisible = !serverFields.classList.contains('hidden');
      const selectedServices = collectSelectedServerServices({
        select: serverServiceSelect,
        checkboxes: serverServiceCheckboxes,
      });
      serverServiceFieldGroups.forEach((group) => {
        const service = normalizeServerService(group.dataset.serverServiceFields || '');
        const show = isServerVisible && selectedServices.includes(service);

        group.classList.toggle('hidden', !show);
        group.querySelectorAll('input, select, textarea').forEach((field) => {
          field.disabled = !show;
        });
        group.querySelectorAll('[data-server-service-address-required]').forEach((field) => {
          field.required = show;
        });
        group.querySelectorAll('[data-server-service-vnc-required]').forEach((field) => {
          field.required = show;
        });
      });
    };

    const resetServerInputs = () => {
      serverInputs.forEach((field) => {
        const defaultValue = serverDefaults.get(field) ?? '';
        if (field.type === 'checkbox' || field.type === 'radio') {
          field.checked = Boolean(defaultValue);
          return;
        }
        if (field.tagName === 'SELECT' && field.multiple) {
          const defaults = Array.isArray(defaultValue) ? new Set(defaultValue) : new Set();
          Array.from(field.options || []).forEach((option) => {
            option.selected = defaults.has(option.value);
          });
          return;
        }
        field.value = defaultValue;
      });
    };

    const hideServerFields = (reset = false) => {
      if (!serverFields) {
        return;
      }
      serverFields.classList.add('hidden');
      serverInputs.forEach((field) => {
        field.disabled = true;
      });
      serverRequired.forEach((field) => {
        field.required = false;
      });
      if (reset) {
        resetServerInputs();
      }
      toggleServerStandaloneFields();
      toggleServerServiceFields();
    };

    const showServerFields = () => {
      if (!serverFields) {
        return;
      }
      serverFields.classList.remove('hidden');
      serverInputs.forEach((field) => {
        field.disabled = false;
      });
      serverRequired.forEach((field) => {
        field.required = true;
      });
      toggleServerStandaloneFields();
      toggleServerServiceFields();
    };

    const resetOltInputs = () => {
      oltInputs.forEach((field) => {
        const defaultValue = oltDefaults.get(field) ?? '';
        if (field.type === 'checkbox' || field.type === 'radio') {
          field.checked = Boolean(defaultValue);
          return;
        }
        field.value = defaultValue;
      });
      if (oltFolderLocation) {
        oltFolderLocation.dataset.auto = oltFolderLocation.value ? 'false' : 'true';
      }
    };

    const hideOltFields = (reset = false) => {
      if (!oltFields) {
        return;
      }
      oltFields.classList.add('hidden');
      oltInputs.forEach((field) => {
        field.disabled = true;
      });
      oltRequired.forEach((field) => {
        field.required = false;
      });
      if (reset) {
        resetOltInputs();
      }
    };

    const showOltFields = () => {
      if (!oltFields) {
        return;
      }
      oltFields.classList.remove('hidden');
      oltInputs.forEach((field) => {
        field.disabled = false;
      });
      oltRequired.forEach((field) => {
        field.required = true;
      });
      updateOltFolderLocation();
    };

    const resetMikrotikInputs = () => {
      mikrotikInputs.forEach((field) => {
        const defaultValue = mikrotikDefaults.get(field) ?? '';
        if (field.type === 'checkbox' || field.type === 'radio') {
          field.checked = Boolean(defaultValue);
          return;
        }
        field.value = defaultValue;
      });
    };

    const hideMikrotikFields = (reset = false) => {
      if (!mikrotikFields) {
        return;
      }
      mikrotikFields.classList.add('hidden');
      mikrotikInputs.forEach((field) => {
        field.disabled = true;
      });
      mikrotikRequired.forEach((field) => {
        field.required = false;
      });
      if (reset) {
        resetMikrotikInputs();
      }
    };

    const showMikrotikFields = () => {
      if (!mikrotikFields) {
        return;
      }
      mikrotikFields.classList.remove('hidden');
      mikrotikInputs.forEach((field) => {
        field.disabled = false;
      });
      mikrotikRequired.forEach((field) => {
        field.required = true;
      });
    };

    const toggleTypeSections = () => {
      const isCisco = (typeSelect.value || '').toUpperCase() === 'CISCO';
      const isMimosa = (typeSelect.value || '').toUpperCase() === 'MIMOSA';
      const isServer = (typeSelect.value || '').toUpperCase() === 'SERVER';
      const isOlt = (typeSelect.value || '').toUpperCase() === 'OLT';
      const isMikrotik = (typeSelect.value || '').toUpperCase() === 'MIKROTIK';
      if (!isCisco) {
        hideCiscoFields(true);
      } else {
        revealCiscoFields();
      }

      if (isMimosa) {
        showMimosaFields();
      } else {
        hideMimosaFields();
      }

      if (isServer) {
        showServerFields();
      } else {
        hideServerFields(true);
      }

      if (isOlt) {
        showOltFields();
      } else {
        hideOltFields(true);
      }

      if (isMikrotik) {
        showMikrotikFields();
      } else {
        hideMikrotikFields(true);
      }
    };

    if (ciscoName) {
      ciscoName.addEventListener('input', updateAutoFields);
    }
    if (oltName) {
      oltName.addEventListener('input', updateOltFolderLocation);
    }
    if (ciscoSwitchModel) {
      ciscoSwitchModel.addEventListener('change', toggleCiscoUsernameFields);
    }

    if (mimosaModel) {
      mimosaModel.addEventListener('change', showMimosaForm);
    }
    if (serverType) {
      serverType.addEventListener('change', toggleServerStandaloneFields);
    }
    if (serverServiceSelect) {
      serverServiceSelect.addEventListener('change', toggleServerServiceFields);
    }
    serverServiceCheckboxes.forEach((input) => {
      input.addEventListener('change', toggleServerServiceFields);
    });

    typeSelect.addEventListener('change', toggleTypeSections);
    toggleTypeSections();
  };

  const setupUserEditToggles = () => {
    if (window.__userEditTogglesBound) {
      return;
    }
    window.__userEditTogglesBound = true;

    const toggleRow = (userId, show) => {
      const row = document.querySelector(`[data-user-edit-row=\"${userId}\"]`);
      if (!row) {
        return;
      }
      if (typeof show === 'boolean') {
        row.classList.toggle('hidden', !show);
      } else {
        row.classList.toggle('hidden');
      }
    };

    document.addEventListener('click', (event) => {
      const editButton = event.target.closest('[data-user-edit]');
      if (editButton) {
        event.preventDefault();
        event.stopPropagation();
        toggleRow(editButton.dataset.userEdit);
        return;
      }

      const closeButton = event.target.closest('[data-user-edit-close]');
      if (closeButton) {
        event.preventDefault();
        event.stopPropagation();
        toggleRow(closeButton.dataset.userEditClose, false);
      }
    });
  };

  const setupDevicePermissionPortInputs = () => {
    const containers = Array.from(document.querySelectorAll('[data-device-port-permissions]'));
    if (!containers.length) {
      return;
    }

    containers.forEach((container) => {
      const form = container.closest('form');
      if (!form) {
        return;
      }
      const select = form.querySelector('select[name="device_permission_ids[]"]');
      const checkboxes = Array.from(
        form.querySelectorAll(
          'input[type="checkbox"][name="device_permission_ids[]"][data-device-permission-checkbox]'
        )
      );
      if (!select && !checkboxes.length) {
        return;
      }

      const items = Array.from(container.querySelectorAll('[data-device-port-item][data-device-id]'));
      if (!items.length) {
        return;
      }
      const empty = container.querySelector('[data-device-port-empty]');

      const updateVisibility = () => {
        const selectedIds = new Set(
          select
            ? Array.from(select.selectedOptions || []).map((option) => String(option.value || ''))
            : checkboxes
                .filter((item) => item.checked)
                .map((item) => String(item.value || ''))
        );

        let visibleCount = 0;
        items.forEach((item) => {
          const deviceId = String(item.dataset.deviceId || '');
          const visible = selectedIds.has(deviceId);
          item.classList.toggle('hidden', !visible);
          if (visible) {
            visibleCount += 1;
          }
        });

        if (empty) {
          empty.classList.toggle('hidden', visibleCount > 0);
        }
      };

      if (select) {
        select.addEventListener('change', updateVisibility);
      }
      checkboxes.forEach((item) => {
        item.addEventListener('change', updateVisibility);
      });
      updateVisibility();
    });
  };

  const setupDevicePermissionCommandInputs = () => {
    const containers = Array.from(document.querySelectorAll('[data-device-command-permissions]'));
    if (!containers.length) {
      return;
    }

    containers.forEach((container) => {
      const form = container.closest('form');
      if (!form) {
        return;
      }
      const select = form.querySelector('select[name="device_permission_ids[]"]');
      const checkboxes = Array.from(
        form.querySelectorAll(
          'input[type="checkbox"][name="device_permission_ids[]"][data-device-permission-checkbox]'
        )
      );
      if (!select && !checkboxes.length) {
        return;
      }

      const items = Array.from(container.querySelectorAll('[data-device-command-item][data-device-id]'));
      if (!items.length) {
        return;
      }

      const empty = container.querySelector('[data-device-command-empty]');

      const updateVisibility = () => {
        const selectedIds = new Set(
          select
            ? Array.from(select.selectedOptions || []).map((option) => String(option.value || ''))
            : checkboxes
                .filter((item) => item.checked)
                .map((item) => String(item.value || ''))
        );

        let visibleCount = 0;
        items.forEach((item) => {
          const deviceId = String(item.dataset.deviceId || '');
          const visible = selectedIds.has(deviceId);
          item.classList.toggle('hidden', !visible);
          if (visible) {
            visibleCount += 1;
          }
        });

        if (empty) {
          empty.classList.toggle('hidden', visibleCount > 0);
        }
      };

      if (select) {
        select.addEventListener('change', updateVisibility);
      }
      checkboxes.forEach((item) => {
        item.addEventListener('change', updateVisibility);
      });
      updateVisibility();
    });
  };

  const setupGraphDeviceInterfaceInputs = () => {
    const containers = Array.from(document.querySelectorAll('[data-device-graph-interface-permissions]'));
    if (!containers.length) {
      return;
    }

    containers.forEach((container) => {
      const form = container.closest('form');
      if (!form) {
        return;
      }
      const select = form.querySelector('select[name="graph_device_ids[]"]');
      const checkboxes = Array.from(
        form.querySelectorAll(
          'input[type="checkbox"][name="graph_device_ids[]"][data-graph-device-checkbox]'
        )
      );
      if (!select && !checkboxes.length) {
        return;
      }

      const items = Array.from(
        container.querySelectorAll('[data-device-graph-interface-item][data-device-id]')
      );
      if (!items.length) {
        return;
      }

      const empty = container.querySelector('[data-device-graph-interface-empty]');

      const updateVisibility = () => {
        const selectedIds = new Set(
          select
            ? Array.from(select.selectedOptions || []).map((option) => String(option.value || ''))
            : checkboxes
                .filter((item) => item.checked)
                .map((item) => String(item.value || ''))
        );

        let visibleCount = 0;
        items.forEach((item) => {
          const deviceId = String(item.dataset.deviceId || '');
          const visible = selectedIds.has(deviceId);
          item.classList.toggle('hidden', !visible);
          if (visible) {
            visibleCount += 1;
          }
        });

        if (empty) {
          empty.classList.toggle('hidden', visibleCount > 0);
        }
      };

      if (select) {
        select.addEventListener('change', updateVisibility);
      }
      checkboxes.forEach((item) => {
        item.addEventListener('change', updateVisibility);
      });
      updateVisibility();
    });
  };

  const setupMultiSelectShortcuts = () => {
    const groups = Array.from(document.querySelectorAll('[data-multi-select]'));
    if (!groups.length) {
      return;
    }

    groups.forEach((group) => {
      if (group.dataset.multiSelectBound === 'true') {
        return;
      }

      const select = group.querySelector('[data-multi-select-input], select[multiple]');
      if (!select) {
        return;
      }

      const countTarget = group.querySelector('[data-selected-count]');
      const updateCount = () => {
        if (!countTarget) {
          return;
        }
        const selectedCount = Array.from(select.selectedOptions || []).length;
        countTarget.textContent = String(selectedCount);
      };

      group.querySelectorAll('[data-select-action]').forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          const mode = String(button.dataset.selectAction || '').toLowerCase();
          const options = Array.from(select.options || []);
          if (!options.length) {
            return;
          }

          if (mode === 'all') {
            options.forEach((option) => {
              option.selected = true;
            });
          } else if (mode === 'none') {
            options.forEach((option) => {
              option.selected = false;
            });
          } else if (mode === 'invert') {
            options.forEach((option) => {
              option.selected = !option.selected;
            });
          }

          select.dispatchEvent(new Event('change', { bubbles: true }));
          updateCount();
        });
      });

      select.addEventListener('change', updateCount);
      updateCount();
      group.dataset.multiSelectBound = 'true';
    });
  };

  const setupCheckboxShortcuts = () => {
    const groups = Array.from(document.querySelectorAll('[data-checkbox-group]'));
    if (!groups.length) {
      return;
    }

    groups.forEach((group) => {
      if (group.dataset.checkboxGroupBound === 'true') {
        return;
      }

      const getItems = () =>
        Array.from(group.querySelectorAll('input[type="checkbox"][data-checkbox-item]'));
      const countTarget = group.querySelector('[data-checkbox-count]');

      const updateCount = () => {
        if (!countTarget) {
          return;
        }
        const checkedCount = getItems().filter((item) => item.checked).length;
        countTarget.textContent = String(checkedCount);
      };

      group.querySelectorAll('[data-checkbox-action]').forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          const mode = String(button.dataset.checkboxAction || '').toLowerCase();
          const items = getItems();
          if (!items.length) {
            return;
          }

          if (mode === 'all') {
            items.forEach((item) => {
              item.checked = true;
            });
          } else if (mode === 'none') {
            items.forEach((item) => {
              item.checked = false;
            });
          } else if (mode === 'invert') {
            items.forEach((item) => {
              item.checked = !item.checked;
            });
          }

          items.forEach((item) => {
            item.dispatchEvent(new Event('change', { bubbles: true }));
          });
          updateCount();
        });
      });

      getItems().forEach((item) => {
        item.addEventListener('change', updateCount);
      });

      updateCount();
      group.dataset.checkboxGroupBound = 'true';
    });
  };

  const setupCustomCommandPermissions = () => {
    const builders = Array.from(document.querySelectorAll('[data-custom-command-builder]'));
    if (!builders.length) {
      return;
    }

    builders.forEach((builder) => {
      if (builder.dataset.customCommandBound === 'true') {
        return;
      }

      const typeSelect = builder.querySelector('[data-custom-command-type]');
      const customFields = builder.querySelector('[data-custom-command-fields]');
      if (!typeSelect || !customFields) {
        return;
      }

      const scriptNameInput = customFields.querySelector(
        'input[name="custom_command_script_name"], input[name="script_name"]'
      );
      const scriptCodeInput = customFields.querySelector(
        'textarea[name="custom_command_script_code"], textarea[name="script_code"]'
      );

      const updateState = () => {
        const isCustom = String(typeSelect.value || '').toLowerCase() === 'custom';
        customFields.classList.toggle('hidden', !isCustom);
        if (scriptNameInput) {
          scriptNameInput.required = isCustom;
        }
        if (scriptCodeInput) {
          scriptCodeInput.required = isCustom;
        }
      };

      typeSelect.addEventListener('change', updateState);
      updateState();
      builder.dataset.customCommandBound = 'true';
    });
  };

  const setupDeviceEditToggles = () => {
    if (window.__deviceEditTogglesBound) {
      return;
    }
    window.__deviceEditTogglesBound = true;

    const toggleRow = (deviceId, show) => {
      const row = document.querySelector(`[data-device-edit-row=\"${deviceId}\"]`);
      if (!row) {
        return;
      }
      if (typeof show === 'boolean') {
        row.classList.toggle('hidden', !show);
      } else {
        row.classList.toggle('hidden');
      }
    };

    document.addEventListener('click', (event) => {
      const editButton = event.target.closest('[data-device-edit]');
      if (editButton) {
        event.preventDefault();
        event.stopPropagation();
        toggleRow(editButton.dataset.deviceEdit);
        return;
      }

      const closeButton = event.target.closest('[data-device-edit-close]');
      if (closeButton) {
        event.preventDefault();
        event.stopPropagation();
        toggleRow(closeButton.dataset.deviceEditClose, false);
      }
    });
  };

  const setupDeviceEditTypeFields = () => {
    const forms = Array.from(document.querySelectorAll('[data-device-edit-form]'));
    if (!forms.length) {
      return;
    }

    const toggleGroup = (group, show, requiredAttribute = null) => {
      if (!group) {
        return;
      }
      group.classList.toggle('hidden', !show);
      group.querySelectorAll('input, select, textarea').forEach((field) => {
        field.disabled = !show;
      });
      if (requiredAttribute) {
        group.querySelectorAll(`[${requiredAttribute}]`).forEach((field) => {
          field.required = show;
        });
      }
    };

    forms.forEach((form) => {
      if (form.dataset.deviceTypeBound === 'true') {
        return;
      }
      const typeSelect = form.querySelector('[data-device-edit-type]');
      if (!typeSelect) {
        return;
      }
      const ciscoFields = form.querySelector('[data-device-edit-cisco-fields]');
      const serverFields = form.querySelector('[data-device-edit-server-fields]');
      const oltFields = form.querySelector('[data-device-edit-olt-fields]');
      const mikrotikFields = form.querySelector('[data-device-edit-mikrotik-fields]');
      const commonNameField = form.querySelector('[data-device-edit-common-name-field]');
      const commonNameInput = commonNameField ? commonNameField.querySelector('input[name="name"]') : null;
      const oltFolderLocationInput = oltFields ? oltFields.querySelector('input[name="olt_folder_location"]') : null;
      const serverTypeSelect = form.querySelector('[data-device-edit-server-type]');
      const serverServiceSelect = form.querySelector('select[data-device-edit-server-service]');
      const serverServiceCheckboxes = Array.from(form.querySelectorAll('[data-device-edit-server-service-option]'));
      const serverStandaloneFields = Array.from(form.querySelectorAll('[data-device-edit-server-standalone-field]'));
      const serverServiceFieldGroups = Array.from(form.querySelectorAll('[data-device-edit-server-service-fields]'));
      const genericIpField = form.querySelector('[data-device-edit-generic-ip-field]');
      const snmpCommunityField = form.querySelector('[data-device-edit-snmp-community-field]');
      const snmpPortField = form.querySelector('[data-device-edit-snmp-port-field]');
      const ciscoUsernameFields = Array.from(form.querySelectorAll('[data-device-edit-cisco-username-field]'));
      const ciscoModel = String(ciscoFields?.dataset.deviceEditCiscoModel || '');

      const normalizeFolderName = (value) =>
        String(value || '')
          .trim()
          .replace(/[\\/]+/g, '-')
          .replace(/\s+/g, '_');

      const updateEditOltFolderLocation = (isOlt) => {
        if (!isOlt || !commonNameInput || !oltFolderLocationInput) {
          return;
        }

        const isAuto = oltFolderLocationInput.dataset.auto !== 'false';
        if (!isAuto) {
          return;
        }

        const normalizedName = normalizeFolderName(commonNameInput.value);
        oltFolderLocationInput.value = normalizedName ? `uno/${normalizedName}` : '';
        oltFolderLocationInput.dataset.auto = 'true';
      };

      if (oltFolderLocationInput) {
        oltFolderLocationInput.dataset.auto = oltFolderLocationInput.value ? 'false' : 'true';
        oltFolderLocationInput.addEventListener('input', () => {
          oltFolderLocationInput.dataset.auto = 'false';
        });
      }

      const toggleServerStandalone = (serverVisible) => {
        const selectedType = String(serverTypeSelect?.value || 'virtual_server').toLowerCase();
        const isStandalone = serverVisible && selectedType === 'stand_alone_server';

        serverStandaloneFields.forEach((group) => {
          group.classList.toggle('hidden', !isStandalone);
          group.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = !isStandalone;
          });
          group.querySelectorAll('[data-device-edit-server-standalone-required]').forEach((field) => {
            field.required = isStandalone;
          });
        });
      };

      const toggleServerServiceFields = (serverVisible) => {
        const selectedServices = collectSelectedServerServices({
          select: serverServiceSelect,
          checkboxes: serverServiceCheckboxes,
        });
        serverServiceFieldGroups.forEach((group) => {
          const service = normalizeServerService(group.dataset.deviceEditServerServiceFields || '');
          const show = serverVisible && selectedServices.includes(service);

          group.classList.toggle('hidden', !show);
          group.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = !show;
          });
          group.querySelectorAll('[data-device-edit-server-service-address-required]').forEach((field) => {
            field.required = show;
          });
          group.querySelectorAll('[data-device-edit-server-service-vnc-required]').forEach((field) => {
            field.required = show;
          });
        });
      };

      const toggleCiscoUsername = (ciscoVisible) => {
        const showUsername = ciscoVisible && ciscoModelUsesUsername(ciscoModel);

        ciscoUsernameFields.forEach((group) => {
          group.classList.toggle('hidden', !showUsername);
          group.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = !showUsername;
          });
        });
      };

      const updateType = () => {
        const type = (typeSelect.value || '').toUpperCase();
        const isCisco = type === 'CISCO';
        const isMimosa = type === 'MIMOSA';
        const isServer = type === 'SERVER';
        const isOlt = type === 'OLT';
        const isMikrotik = type === 'MIKROTIK';
        const showSnmpPort = isCisco || isServer || isMimosa;
        const showCommonName = isOlt || isMikrotik;

        toggleGroup(ciscoFields, isCisco);
        toggleGroup(serverFields, isServer, 'data-device-edit-server-required');
        toggleGroup(oltFields, isOlt, 'data-device-edit-olt-required');
        toggleGroup(mikrotikFields, isMikrotik, 'data-device-edit-mikrotik-required');
        toggleCiscoUsername(isCisco);
        toggleServerStandalone(isServer);
        toggleServerServiceFields(isServer);
        updateEditOltFolderLocation(isOlt);
        toggleGroup(commonNameField, showCommonName, 'data-device-edit-common-name-required');

        if (genericIpField) {
          genericIpField.classList.toggle('hidden', isMikrotik || isOlt);
          genericIpField.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = isMikrotik || isOlt;
          });
        }
        if (snmpCommunityField) {
          snmpCommunityField.classList.toggle('hidden', isMikrotik || isOlt);
          snmpCommunityField.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = isMikrotik || isOlt;
          });
        }

        if (snmpPortField) {
          snmpPortField.classList.toggle('hidden', !showSnmpPort);
          snmpPortField.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = !showSnmpPort;
          });
        }
      };

      typeSelect.addEventListener('change', updateType);
      if (commonNameInput) {
        commonNameInput.addEventListener('input', () => {
          const isOlt = (typeSelect.value || '').toUpperCase() === 'OLT';
          updateEditOltFolderLocation(isOlt);
        });
      }
      if (serverTypeSelect) {
        serverTypeSelect.addEventListener('change', () => {
          const isServer = (typeSelect.value || '').toUpperCase() === 'SERVER';
          toggleServerStandalone(isServer);
        });
      }
      if (serverServiceSelect) {
        serverServiceSelect.addEventListener('change', () => {
          const isServer = (typeSelect.value || '').toUpperCase() === 'SERVER';
          toggleServerServiceFields(isServer);
        });
      }
      serverServiceCheckboxes.forEach((input) => {
        input.addEventListener('change', () => {
          const isServer = (typeSelect.value || '').toUpperCase() === 'SERVER';
          toggleServerServiceFields(isServer);
        });
      });
      updateType();
      form.dataset.deviceTypeBound = 'true';
    });
  };

  const setupDeviceRowLinks = () => {
    const rows = Array.from(document.querySelectorAll('[data-device-link]'));
    if (!rows.length) {
      return;
    }
    const drawer = document.querySelector('[data-device-drawer]');
    let loading = false;

    const isInteractive = (target) =>
      Boolean(target.closest('a, button, input, select, textarea, label, form'));

    const selectRow = (deviceId) => {
      rows.forEach((candidate) => {
        const isSelected = String(candidate.dataset.deviceId || '') === String(deviceId || '');
        candidate.classList.toggle('bg-primary/5', isSelected);
      });
    };

    const updateDrawer = async (link, deviceId) => {
      if (!drawer) {
        window.location.href = link;
        return;
      }
      if (loading) {
        return;
      }
      loading = true;

      try {
        const response = await fetch(link, {
          method: 'GET',
          headers: {
            Accept: 'text/html',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
          cache: 'no-store',
        });
        if (!response.ok) {
          throw new Error(`Request failed (${response.status})`);
        }

        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const nextDrawer = doc.querySelector('[data-device-drawer]');
        if (!nextDrawer) {
          throw new Error('Drawer content missing in response');
        }

        drawer.innerHTML = nextDrawer.innerHTML;
        drawer.dataset.deviceId = nextDrawer.dataset.deviceId || String(deviceId || '');
        selectRow(deviceId);

        if (window.history && typeof window.history.replaceState === 'function') {
          window.history.replaceState(window.history.state || {}, '', link);
        }
      } catch (error) {
        window.location.href = link;
      } finally {
        loading = false;
      }
    };

    rows.forEach((row) => {
      const link = row.dataset.deviceLink;
      if (!link) {
        return;
      }

      const openRow = () => {
        const deviceId = row.dataset.deviceId || '';
        if (drawer) {
          updateDrawer(link, deviceId);
          return;
        }
        window.location.href = link;
      };

      row.querySelectorAll('a[href]').forEach((anchor) => {
        const href = anchor.getAttribute('href') || '';
        if (href !== link) {
          return;
        }
        anchor.addEventListener('click', (event) => {
          if (!drawer) {
            return;
          }
          event.preventDefault();
          openRow();
        });
      });

      row.addEventListener('click', (event) => {
        if (isInteractive(event.target)) {
          return;
        }
        openRow();
      });

      row.addEventListener('keydown', (event) => {
        if (event.target !== row) {
          return;
        }
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          openRow();
        }
      });
    });
  };

  const setupAddDeviceToggle = () => {
    const details = document.getElementById('add-device-form');
    if (!details || details.tagName !== 'DETAILS') {
      return;
    }

    const openDetails = () => {
      details.open = true;
      details.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const maybeOpenFromHash = () => {
      if (window.location.hash === '#add-device-form') {
        openDetails();
      }
    };

    document.querySelectorAll('a[href="#add-device-form"]').forEach((link) => {
      link.addEventListener('click', () => {
        openDetails();
      });
    });

    window.addEventListener('hashchange', maybeOpenFromHash);
    maybeOpenFromHash();
  };

  const setupStatusPolling = () => {
    const getRows = () => Array.from(document.querySelectorAll('[data-device-row][data-device-id]'));
    let rows = getRows();
    if (!rows.length) {
      return;
    }


    const statusEndpoint = appBase ? `${appBase}/devices/status-snapshot` : '/devices/status-snapshot';

    const normalizeStatus = (value) => {
      if (!value) {
        return 'offline';
      }
      return String(value).toLowerCase();
    };

    const formatStatusText = (status, mode) => {
      if (mode === 'yesno') {
        return status === 'online' ? 'YES' : 'NO';
      }
      return status.charAt(0).toUpperCase() + status.slice(1);
    };

    const formatUptime = (value) => {
      if (!value) {
        return "-";
      }
      const raw = String(value).trim();
      if (!raw) {
        return "-";
      }
      if (/^\d+$/.test(raw)) {
        return raw;
      }

      const lower = raw.toLowerCase();
      let days = 0;
      let hours = 0;
      let minutes = 0;

      const unitRegex = /(\d+)\s*(year|week|day)s?/g;
      let match;
      while ((match = unitRegex.exec(lower)) !== null) {
        const count = Number(match[1]);
        const unit = match[2];
        if (unit === "year") {
          days += count * 365;
        } else if (unit === "week") {
          days += count * 7;
        } else {
          days += count;
        }
      }

      const hourMatch = /(\d+)\s*hour/.exec(lower);
      if (hourMatch) {
        hours = Number(hourMatch[1]);
      }
      const minuteMatch = /(\d+)\s*minute/.exec(lower);
      if (minuteMatch) {
        minutes = Number(minuteMatch[1]);
      }

      const dayTimeMatch = /(\d+)\s*day.*?(\d+):(\d+)/.exec(lower);
      if (dayTimeMatch) {
        days += Number(dayTimeMatch[1]);
        hours = Number(dayTimeMatch[2]);
        minutes = Number(dayTimeMatch[3]);
      } else {
        const hmsMatch = /(\d+):(\d+):(\d+)/.exec(lower);
        if (hmsMatch) {
          hours = Number(hmsMatch[1]);
          minutes = Number(hmsMatch[2]);
        } else {
          const hmMatch = /(\d+):(\d+)/.exec(lower);
          if (hmMatch) {
            hours = Number(hmMatch[1]);
            minutes = Number(hmMatch[2]);
          }
        }
      }

      if (days === 0 && hours === 0 && minutes === 0) {
        return raw;
      }

      const parts = [];
      if (days > 0) {
        parts.push(`${days}d`);
      }
      if (hours > 0 || days > 0) {
        parts.push(`${hours}h`);
      }
      parts.push(`${minutes}m`);
      return parts.join(" ");
    };

    const applyBadge = (badge, status) => {
      const base = badge.dataset.statusBase || '';
      const key = `status${status.charAt(0).toUpperCase()}${status.slice(1)}`;
      const cls = badge.dataset[key] || badge.dataset.statusOffline || '';
      badge.className = `${base} ${cls}`.trim();

      const text = formatStatusText(status, badge.dataset.statusMode || 'default');
      const textEl = badge.querySelector('[data-status-text]');
      if (textEl) {
        textEl.textContent = text;
      } else {
        badge.textContent = text;
      }
    };

    const applyDevice = (row, device) => {
      const status = normalizeStatus(device.status);
      row.querySelectorAll('[data-status-badge]').forEach((badge) => applyBadge(badge, status));
      row.querySelectorAll('[data-status-text]').forEach((el) => {
        if (el.closest('[data-status-badge]')) {
          return;
        }
        el.textContent = formatStatusText(status, el.dataset.statusMode || 'default');
      });
      row.querySelectorAll('[data-last-seen]').forEach((el) => {
        el.textContent = device.last_seen_human || '-';
      });
      const degree = '\u00B0';
      let tempRaw = String(device.temperature ?? '');
      tempRaw = tempRaw
        .replace(/\uFFFD/g, degree)
        .replace(/\u00C2\u00B0/g, degree)
        .replace(/\u00C2/g, '')
        .replace(/\$/g, degree)
        .replace(/\?/g, degree)
        .replace(/\b([0-9]+)\s*[^0-9]*C\b/gi, (_, num) => `${num}${degree}C`)
        .replace(/\b(inlet|outlet|temp):\s*([0-9]+)\s*[^0-9]*C\b/gi, (_, label, num) => `${label}: ${num}${degree}C`);
      const inletMatch = /inlet:\s*([0-9]+)/i.exec(tempRaw);
      const outletMatch = /outlet:\s*([0-9]+)/i.exec(tempRaw);
      const singleMatch = /temp:\s*([0-9]+)/i.exec(tempRaw);

      row.querySelectorAll('[data-temp-inlet]').forEach((el) => {
        el.textContent = inletMatch ? `${inletMatch[1]}${degree}C` : '-';
      });
      row.querySelectorAll('[data-temp-outlet]').forEach((el) => {
        el.textContent = outletMatch ? `${outletMatch[1]}${degree}C` : '-';
      });
      row.querySelectorAll('[data-temp-single]').forEach((el) => {
        el.textContent = singleMatch ? `temp: ${singleMatch[1]}${degree}C` : (tempRaw || '-');
      });
      row.querySelectorAll('[data-temp-value]').forEach((el) => {
        el.textContent = tempRaw || '-';
      });
      row.querySelectorAll('[data-uptime-value]').forEach((el) => {
        el.textContent = formatUptime(device.uptime);
      });
      row.querySelectorAll('[data-temp-updated]').forEach((el) => {
        el.textContent = `updated ${device.last_seen_formatted || '-'}`;
      });
      row.querySelectorAll('[data-uptime-updated]').forEach((el) => {
        el.textContent = `updated ${device.last_seen_formatted || '-'}`;
      });
      row.querySelectorAll('[data-signal-value]').forEach((el) => {
        el.textContent = device.signal || '-';
      });
      row.querySelectorAll('[data-battery-value]').forEach((el) => {
        el.textContent = device.battery || '-';
      });
    };

    const pollTimerState = { timer: null, inFlight: false };
    const scheduleNextPoll = () => {
      if (pollTimerState.timer) {
        clearTimeout(pollTimerState.timer);
      }
      pollTimerState.timer = setTimeout(poll, 5000);
    };

    const poll = async () => {
      if (pollTimerState.inFlight) {
        scheduleNextPoll();
        return;
      }
      pollTimerState.inFlight = true;
      try {
        rows = getRows();
        if (!rows.length) {
          return;
        }
        const ids = rows.map((row) => row.dataset.deviceId).filter(Boolean);
        if (!ids.length) {
          return;
        }
        const query = new URLSearchParams({ ids: ids.join(','), probe: 1, t: Date.now() });
        const response = await fetch(`${statusEndpoint}?${query.toString()}`, {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
          cache: 'no-store',
        });
        if (!response.ok) {
          return;
        }
        const payload = await response.json();
        const devices = Array.isArray(payload.devices) ? payload.devices : [];
        const map = new Map(devices.map((device) => [String(device.id), device]));

        rows.forEach((row) => {
          const device = map.get(String(row.dataset.deviceId || ''));
          if (!device) {
            return;
          }
          applyDevice(row, device);
        });
      } catch (error) {
        // keep silent to avoid noisy UI
      } finally {
        pollTimerState.inFlight = false;
        scheduleNextPoll();
      }
    };

    poll();
  };

  const setupBackupActions = () => {
    const buttons = Array.from(document.querySelectorAll('[data-backup-run]'));
    if (!buttons.length) {
      return;
    }

    const escapeHtml = (value) => String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

    const renderPanel = (button, payload = {}) => {
      const row = button.closest('[data-device-row]');
      const panel = row ? row.querySelector('[data-backup-panel]') : null;
      if (!panel) {
        return;
      }
      const statusEl = panel.querySelector('[data-backup-status]');
      const listEl = panel.querySelector('[data-backup-files]');
      const files = Array.isArray(payload.files) ? payload.files : [];
      panel.classList.remove('hidden');

      if (!files.length) {
        if (statusEl) {
          statusEl.textContent = payload.message || 'No backups yet.';
        }
        if (listEl) {
          listEl.innerHTML = '';
        }
        return;
      }

      if (statusEl) {
        const folder = payload.folder ? `Folder: ${payload.folder}` : 'Backup files';
        statusEl.textContent = folder;
      }

      if (!listEl) {
        return;
      }

      listEl.innerHTML = files.map((file) => {
        const name = escapeHtml(file.name || 'backup.txt');
        const href = escapeHtml(file.download_url || '#');
        const modified = escapeHtml(file.modified_at || '-');
        const size = escapeHtml(file.size_human || '-');
        return `<li class="flex items-center justify-between gap-2"><a class="text-primary hover:underline truncate" href="${href}" target="_blank" rel="noopener">${name}</a><span class="text-gray-400 whitespace-nowrap">${modified} (${size})</span></li>`;
      }).join('');
    };

    const loadBackups = async (button) => {
      const listUrl = button.dataset.backupListUrl;
      if (!listUrl) {
        return;
      }
      try {
        const response = await fetch(listUrl, {
          method: 'GET',
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
          cache: 'no-store',
        });
        if (!response.ok) {
          renderPanel(button, { message: 'Unable to load backups.' });
          return;
        }
        const payload = await response.json();
        renderPanel(button, payload);
      } catch (error) {
        renderPanel(button, { message: 'Unable to load backups.' });
      }
    };

    buttons.forEach((button) => {
      const originalLabel = button.textContent;
      loadBackups(button);

      button.addEventListener('click', async () => {
        const runUrl = button.dataset.backupRunUrl;
        if (!runUrl || button.dataset.loading === '1') {
          return;
        }

        button.dataset.loading = '1';
        button.disabled = true;
        button.classList.add('opacity-60', 'cursor-not-allowed');
        button.textContent = 'Running...';

        try {
          const response = await fetch(runUrl, {
            method: 'GET',
            headers: {
              'Accept': 'text/plain',
              'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            cache: 'no-store',
          });
          if (!response.ok) {
            const output = await response.text().catch(() => '');
            renderPanel(button, { message: output ? `Backup failed: ${output}` : 'Backup failed.' });
          } else {
            await loadBackups(button);
          }
        } catch (error) {
          renderPanel(button, { message: 'Backup request failed.' });
        } finally {
          button.dataset.loading = '0';
          button.disabled = false;
          button.classList.remove('opacity-60', 'cursor-not-allowed');
          button.textContent = originalLabel;
        }
      });
    });
  };
  const setupSidebarToggle = () => {
    const buttons = Array.from(document.querySelectorAll('[data-sidebar-toggle]'));
    const sidebar = document.querySelector('[data-sidebar]');
    if (!buttons.length || !sidebar) {
      return;
    }

    ensureMobileLayoutStyle();

    const mobileMedia = window.matchMedia(`(max-width: ${mobileBreakpointPx}px)`);
    const isMobile = () => mobileMedia.matches;
    const storageKey = 'sidebar-collapsed';
    const applyDesktopState = (collapsed) => {
      document.body.classList.toggle('sidebar-collapsed', collapsed);
      document.body.classList.remove(mobileSidebarClass);
      buttons.forEach((button) => {
        button.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
        button.setAttribute('aria-expanded', 'false');
      });
    };

    const closeMobile = () => {
      document.body.classList.remove(mobileSidebarClass);
      buttons.forEach((button) => {
        button.setAttribute('aria-expanded', 'false');
      });
    };

    const toggleMobile = () => {
      document.body.classList.remove('sidebar-collapsed');
      const nextOpen = !document.body.classList.contains(mobileSidebarClass);
      document.body.classList.toggle(mobileSidebarClass, nextOpen);
      buttons.forEach((button) => {
        button.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
      });
    };

    const initial = window.localStorage?.getItem(storageKey) === '1';
    applyDesktopState(initial);
    if (isMobile()) {
      document.body.classList.remove('sidebar-collapsed');
    }

    buttons.forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        if (isMobile()) {
          toggleMobile();
          return;
        }

        const next = !document.body.classList.contains('sidebar-collapsed');
        applyDesktopState(next);
        window.localStorage?.setItem(storageKey, next ? '1' : '0');
      });
    });

    document.addEventListener('click', (event) => {
      if (!isMobile() || !document.body.classList.contains(mobileSidebarClass)) {
        return;
      }

      const target = event.target;
      if (target && sidebar.contains(target)) {
        return;
      }
      if (target && target.closest('[data-sidebar-toggle]')) {
        return;
      }

      closeMobile();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') {
        return;
      }
      if (!document.body.classList.contains(mobileSidebarClass)) {
        return;
      }
      closeMobile();
    });

    document.querySelectorAll('[data-sidebar] a').forEach((link) => {
      link.addEventListener('click', () => {
        if (isMobile()) {
          closeMobile();
        }
      });
    });

    const handleBreakpointChange = () => {
      if (isMobile()) {
        document.body.classList.remove('sidebar-collapsed');
        return;
      }
      closeMobile();
      const collapsed = window.localStorage?.getItem(storageKey) === '1';
      applyDesktopState(collapsed);
    };

    if (typeof mobileMedia.addEventListener === 'function') {
      mobileMedia.addEventListener('change', handleBreakpointChange);
    } else if (typeof mobileMedia.addListener === 'function') {
      mobileMedia.addListener(handleBreakpointChange);
    }
  };
  const setupProvisioningToggle = () => {
    const toggles = Array.from(document.querySelectorAll('[data-provisioning-toggle]'));
    if (!toggles.length) {
      return;
    }

    const endpoint = toggles[0].dataset.provisioningEndpoint || '/debug/provisioning-log';
    const tail = document.querySelector('[data-provisioning-tail]');
    const tailEndpoint = tail?.dataset.provisioningLogEndpoint || `${endpoint}?limit=200`;
    const emptyMessage = tail?.dataset.provisioningLogEmpty || 'No log data yet.';
    const tailMeta = document.querySelector('[data-provisioning-tail-meta]');
    const tailBadge = document.querySelector('[data-provisioning-tail-badge]');
    const statusLabel = document.querySelector('[data-provisioning-status]');
    const summaryLabel = document.querySelector('[data-provisioning-summary]');
    const fileStatusLabel = document.querySelector('[data-provisioning-file-status]');
    const progressTitle = document.querySelector('[data-provisioning-progress-title]');
    const progressState = document.querySelector('[data-provisioning-progress-state]');
    const progressTrace = document.querySelector('[data-provisioning-progress-trace]');
    const progressDevice = document.querySelector('[data-provisioning-progress-device]');
    const progressScript = document.querySelector('[data-provisioning-progress-script]');
    const progressProtocol = document.querySelector('[data-provisioning-progress-protocol]');
    const progressLayer = document.querySelector('[data-provisioning-progress-layer]');
    const progressStep = document.querySelector('[data-provisioning-progress-step]');
    const progressUpdated = document.querySelector('[data-provisioning-progress-updated]');
    const eventsList = document.querySelector('[data-provisioning-events]');
    const eventsEmptyMessage = eventsList?.dataset.provisioningEventsEmpty || 'No structured provisioning events available yet.';
    const streamEndpoint = eventsList?.dataset.provisioningStreamEndpoint || '';
    const streamStatus = document.querySelector('[data-provisioning-stream-status]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    let pollTimer = null;
    let isFetching = false;
    let stream = null;
    let streamFallbackActive = false;

    const setTailMeta = (message) => {
      if (tailMeta) {
        tailMeta.textContent = message;
      }
    };

    const setTailBadge = (state = 'live') => {
      if (!tailBadge) {
        return;
      }

      const isLive = state === 'live';
      const isError = state === 'error';

      tailBadge.textContent = isError ? 'Live tail unavailable' : 'Live tail connected';
      tailBadge.classList.toggle('bg-emerald-100', isLive);
      tailBadge.classList.toggle('text-emerald-700', isLive);
      tailBadge.classList.toggle('dark:bg-emerald-900/40', isLive);
      tailBadge.classList.toggle('dark:text-emerald-300', isLive);
      tailBadge.classList.toggle('bg-rose-100', isError);
      tailBadge.classList.toggle('text-rose-700', isError);
      tailBadge.classList.toggle('dark:bg-rose-900/30', isError);
      tailBadge.classList.toggle('dark:text-rose-300', isError);
      tailBadge.classList.toggle('bg-slate-100', !isLive && !isError);
      tailBadge.classList.toggle('text-slate-600', !isLive && !isError);
      tailBadge.classList.toggle('dark:bg-slate-800', !isLive && !isError);
      tailBadge.classList.toggle('dark:text-slate-300', !isLive && !isError);
    };

    const setFileState = (hasLines) => {
      if (fileStatusLabel) {
        fileStatusLabel.textContent = hasLines ? 'File Ready' : 'No File Yet';
        fileStatusLabel.classList.toggle('bg-white', hasLines);
        fileStatusLabel.classList.toggle('text-emerald-700', hasLines);
        fileStatusLabel.classList.toggle('dark:bg-emerald-900/40', hasLines);
        fileStatusLabel.classList.toggle('dark:text-emerald-300', hasLines);
        fileStatusLabel.classList.toggle('bg-slate-100', !hasLines);
        fileStatusLabel.classList.toggle('text-slate-600', !hasLines);
        fileStatusLabel.classList.toggle('dark:bg-slate-800', !hasLines);
        fileStatusLabel.classList.toggle('dark:text-slate-300', !hasLines);
      }
    };

    const setStreamStatus = (state = 'idle') => {
      if (!streamStatus) {
        return;
      }

      streamStatus.classList.remove(
        'bg-emerald-100',
        'text-emerald-700',
        'dark:bg-emerald-900/30',
        'dark:text-emerald-300',
        'bg-amber-100',
        'text-amber-700',
        'dark:bg-amber-900/30',
        'dark:text-amber-300',
        'bg-rose-100',
        'text-rose-700',
        'dark:bg-rose-900/30',
        'dark:text-rose-300',
        'bg-slate-100',
        'text-slate-600',
        'dark:bg-slate-800',
        'dark:text-slate-300'
      );

      const labels = {
        connected: 'SSE live',
        connecting: 'SSE connecting',
        fallback: 'Polling fallback',
        error: 'Stream unavailable',
        idle: 'Polling only',
      };
      const classes = {
        connected: ['bg-emerald-100', 'text-emerald-700', 'dark:bg-emerald-900/30', 'dark:text-emerald-300'],
        connecting: ['bg-amber-100', 'text-amber-700', 'dark:bg-amber-900/30', 'dark:text-amber-300'],
        fallback: ['bg-amber-100', 'text-amber-700', 'dark:bg-amber-900/30', 'dark:text-amber-300'],
        error: ['bg-rose-100', 'text-rose-700', 'dark:bg-rose-900/30', 'dark:text-rose-300'],
        idle: ['bg-slate-100', 'text-slate-600', 'dark:bg-slate-800', 'dark:text-slate-300'],
      };

      streamStatus.textContent = labels[state] || labels.idle;
      (classes[state] || classes.idle).forEach((className) => streamStatus.classList.add(className));
    };

    const setProgressStateClasses = (element, state) => {
      if (!element) {
        return;
      }

      element.classList.remove(
        'bg-amber-100',
        'text-amber-700',
        'dark:bg-amber-900/30',
        'dark:text-amber-300',
        'bg-emerald-100',
        'text-emerald-700',
        'dark:bg-emerald-900/30',
        'dark:text-emerald-300',
        'bg-rose-100',
        'text-rose-700',
        'dark:bg-rose-900/30',
        'dark:text-rose-300',
        'bg-slate-100',
        'text-slate-600',
        'dark:bg-slate-800',
        'dark:text-slate-300'
      );

      const classMap = {
        running: ['bg-amber-100', 'text-amber-700', 'dark:bg-amber-900/30', 'dark:text-amber-300'],
        completed: ['bg-emerald-100', 'text-emerald-700', 'dark:bg-emerald-900/30', 'dark:text-emerald-300'],
        failed: ['bg-rose-100', 'text-rose-700', 'dark:bg-rose-900/30', 'dark:text-rose-300'],
        idle: ['bg-slate-100', 'text-slate-600', 'dark:bg-slate-800', 'dark:text-slate-300'],
      };

      (classMap[state] || classMap.idle).forEach((className) => element.classList.add(className));
    };

    const renderProgress = (progress = null) => {
      const state = progress && progress.state ? progress.state : 'idle';
      const title = progress && progress.title ? progress.title : 'No active script execution';
      const trace = progress && progress.trace ? progress.trace : 'N/A';
      const device = progress && progress.device ? progress.device : 'N/A';
      const script = progress && progress.script ? progress.script : 'N/A';
      const protocol = progress && progress.protocol ? progress.protocol : 'N/A';
      const layer = progress && progress.layer ? progress.layer : 'N/A';
      const step = progress && progress.step ? progress.step : 'Waiting for provisioning activity.';
      const updated = progress && progress.updated_at
        ? `Updated ${progress.updated_at}`
        : 'No provisioning activity captured yet.';

      if (progressTitle) {
        progressTitle.textContent = title;
      }
      if (progressState) {
        progressState.textContent = state.charAt(0).toUpperCase() + state.slice(1);
        setProgressStateClasses(progressState, state);
      }
      if (progressTrace) {
        progressTrace.textContent = trace;
      }
      if (progressDevice) {
        progressDevice.textContent = device;
      }
      if (progressScript) {
        progressScript.textContent = script;
      }
      if (progressProtocol) {
        progressProtocol.textContent = protocol;
      }
      if (progressLayer) {
        progressLayer.textContent = layer;
      }
      if (progressStep) {
        progressStep.textContent = step;
      }
      if (progressUpdated) {
        progressUpdated.textContent = updated;
      }
    };

    const renderLines = (lines) => {
      if (!tail) {
        return;
      }

      tail.innerHTML = '';

      if (!lines.length) {
        const empty = document.createElement('div');
        empty.className = 'text-slate-400';
        empty.textContent = emptyMessage;
        tail.appendChild(empty);
      } else {
        const fragment = document.createDocumentFragment();
        lines.forEach((line) => {
          const row = document.createElement('div');
          row.dataset.logLine = '';
          row.textContent = line;
          fragment.appendChild(row);
        });
        tail.appendChild(fragment);
      }

      tail.scrollTop = tail.scrollHeight;
    };

    const createEventBadge = (state) => {
      const badge = document.createElement('span');
      badge.className = 'inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold';

      const normalized = (state || 'info').toLowerCase();
      const palette = {
        success: ['bg-emerald-100', 'text-emerald-700', 'dark:bg-emerald-900/30', 'dark:text-emerald-300'],
        warning: ['bg-amber-100', 'text-amber-700', 'dark:bg-amber-900/30', 'dark:text-amber-300'],
        failure: ['bg-rose-100', 'text-rose-700', 'dark:bg-rose-900/30', 'dark:text-rose-300'],
        running: ['bg-sky-100', 'text-sky-700', 'dark:bg-sky-900/30', 'dark:text-sky-300'],
        info: ['bg-slate-100', 'text-slate-600', 'dark:bg-slate-800', 'dark:text-slate-300'],
      };

      (palette[normalized] || palette.info).forEach((className) => badge.classList.add(className));
      badge.textContent = normalized.toUpperCase();
      return badge;
    };

    const renderEvents = (events) => {
      if (!eventsList) {
        return;
      }

      const previousScrollTop = eventsList.scrollTop;
      const previousScrollHeight = eventsList.scrollHeight;
      const pinnedToTop = previousScrollTop <= 8;
      const displayEvents = Array.isArray(events) ? [...events].reverse() : [];

      eventsList.innerHTML = '';
      if (!displayEvents.length) {
        const empty = document.createElement('div');
        empty.className = 'px-5 py-4 text-sm text-slate-400';
        empty.textContent = eventsEmptyMessage;
        eventsList.appendChild(empty);
        return;
      }

      const fragment = document.createDocumentFragment();
      displayEvents.forEach((event) => {
        const article = document.createElement('article');
        article.dataset.provisioningEvent = '';
        article.className = 'grid gap-3 px-5 py-4 text-sm text-slate-700 dark:text-slate-200';

        const header = document.createElement('div');
        header.className = 'flex flex-wrap items-center gap-2';
        header.appendChild(createEventBadge(event.state));

        const timestamp = document.createElement('span');
        timestamp.className = 'font-mono text-[11px] text-slate-500';
        timestamp.textContent = event.timestamp || 'N/A';
        header.appendChild(timestamp);

        const layer = document.createElement('span');
        layer.className = 'text-xs font-semibold uppercase tracking-wider text-slate-500';
        layer.textContent = event.layer || 'Internal';
        header.appendChild(layer);

        const protocol = document.createElement('span');
        protocol.className = 'text-xs font-semibold uppercase tracking-wider text-slate-500';
        protocol.textContent = event.protocol || 'N/A';
        header.appendChild(protocol);

        article.appendChild(header);

        const body = document.createElement('div');
        body.className = 'grid gap-2 lg:grid-cols-[minmax(0,180px)_minmax(0,1fr)_minmax(0,1fr)]';

        const device = document.createElement('div');
        device.innerHTML = `<p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Device</p>`;
        const deviceName = document.createElement('p');
        deviceName.className = 'mt-1 font-semibold text-slate-900 dark:text-white';
        deviceName.textContent = event.device_name || event.device_ip || 'N/A';
        device.appendChild(deviceName);
        const deviceMeta = document.createElement('p');
        deviceMeta.className = 'text-xs text-slate-500';
        deviceMeta.textContent = event.device_ip || event.device_hostname || '';
        device.appendChild(deviceMeta);
        body.appendChild(device);

        const request = document.createElement('div');
        request.innerHTML = `<p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Request</p>`;
        const requestSummary = document.createElement('p');
        requestSummary.className = 'mt-1';
        requestSummary.textContent = event.request?.summary || 'N/A';
        request.appendChild(requestSummary);
        body.appendChild(request);

        const response = document.createElement('div');
        response.innerHTML = `<p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Response</p>`;
        const responseSummary = document.createElement('p');
        responseSummary.className = 'mt-1';
        responseSummary.textContent = event.response?.summary || event.message || 'N/A';
        response.appendChild(responseSummary);
        body.appendChild(response);

        article.appendChild(body);

        const footer = document.createElement('div');
        footer.className = 'flex flex-wrap gap-4 text-xs text-slate-500';
        const latency = document.createElement('span');
        latency.textContent = `Latency: ${typeof event.latency_ms === 'number' ? `${event.latency_ms} ms` : 'N/A'}`;
        footer.appendChild(latency);
        const reason = document.createElement('span');
        reason.textContent = `Reason: ${event.reason || 'None'}`;
        footer.appendChild(reason);
        const hints = document.createElement('span');
        hints.textContent = `Hints: ${Array.isArray(event.failure_hints) && event.failure_hints.length ? event.failure_hints.join(', ') : 'None'}`;
        footer.appendChild(hints);
        article.appendChild(footer);

        fragment.appendChild(article);
      });

      eventsList.appendChild(fragment);

      if (pinnedToTop) {
        eventsList.scrollTop = 0;
        return;
      }

      const newScrollHeight = eventsList.scrollHeight;
      const heightDelta = newScrollHeight - previousScrollHeight;
      eventsList.scrollTop = Math.max(0, previousScrollTop + heightDelta);
    };

    const applyState = (enabled, hasLines = false) => {
      toggles.forEach((button) => {
        const label = button.querySelector('[data-provisioning-label]');
        button.dataset.provisioningEnabled = enabled ? '1' : '0';
        button.classList.toggle('bg-emerald-50', enabled);
        button.classList.toggle('text-emerald-700', enabled);
        button.classList.toggle('border-emerald-200', enabled);
        button.classList.toggle('bg-white', !enabled);
        button.classList.toggle('text-slate-700', !enabled);
        button.classList.toggle('border-slate-200', !enabled);
        if (label) {
          label.textContent = enabled ? 'Disable Capture' : 'Enable Capture';
        }

        button.setAttribute('title', 'Debug only: toggles provisioning log capture');
      });

      if (statusLabel) {
        statusLabel.textContent = `Provisioning Log: ${enabled ? 'ON' : 'OFF'}`;
        statusLabel.classList.toggle('text-emerald-700', enabled);
        statusLabel.classList.toggle('dark:text-emerald-300', enabled);
        statusLabel.classList.toggle('text-slate-600', !enabled);
        statusLabel.classList.toggle('dark:text-slate-300', !enabled);
      }

      if (summaryLabel) {
        if (enabled && hasLines) {
          summaryLabel.textContent = 'Log file detected and updating live.';
        } else if (enabled) {
          summaryLabel.textContent = 'Capture enabled. Waiting for provisioning output.';
        } else if (hasLines) {
          summaryLabel.textContent = 'Capture disabled. Showing the last recorded provisioning output.';
        } else {
          summaryLabel.textContent = 'No provisioning output has been written yet.';
        }
      }

      setTailBadge('live');
      setFileState(hasLines);
    };

    const applyPayload = (payload) => {
      const lines = Array.isArray(payload.lines) ? payload.lines : [];
      const events = Array.isArray(payload.events) ? payload.events : [];
      const enabled = typeof payload.enabled !== 'undefined'
        ? !!payload.enabled
        : toggles[0].dataset.provisioningEnabled === '1';

      renderLines(lines);
      renderEvents(events);
      applyState(enabled, lines.length > 0);
      renderProgress(payload.progress || null);
    };

    const fetchLogs = async (force = false) => {
      if (!tailEndpoint || isFetching) {
        return;
      }

      isFetching = true;
      try {
        const response = await fetch(tailEndpoint.includes('?') ? `${tailEndpoint}&limit=200` : `${tailEndpoint}?limit=200`, {
          method: 'GET',
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
        });
        if (!response.ok) {
          return;
        }
        const payload = await response.json();
        applyPayload(payload);
        setTailMeta(`Live tail refresh ${new Date().toLocaleTimeString()}`);
      } catch (error) {
        setTailBadge('error');
        setTailMeta('Live tail unavailable right now. Retrying automatically.');
      } finally {
        isFetching = false;
      }
    };

    const closeStream = () => {
      if (stream) {
        stream.close();
        stream = null;
      }
    };

    const connectStream = () => {
      if (!streamEndpoint || !window.EventSource) {
        setStreamStatus('idle');
        startPolling();
        return;
      }

      closeStream();
      setStreamStatus('connecting');
      stream = new window.EventSource(streamEndpoint.includes('?')
        ? `${streamEndpoint}&limit=200&event_limit=60`
        : `${streamEndpoint}?limit=200&event_limit=60`);

      stream.addEventListener('snapshot', (event) => {
        try {
          const payload = JSON.parse(event.data);
          applyPayload(payload);
          setStreamStatus('connected');
          setTailBadge('live');
          setTailMeta(`Live stream update ${new Date().toLocaleTimeString()}`);
          if (streamFallbackActive) {
            stopPolling();
            streamFallbackActive = false;
          }
        } catch (error) {
          console.warn('Provisioning stream payload error', error);
        }
      });

      stream.onopen = () => {
        setStreamStatus('connected');
        if (streamFallbackActive) {
          stopPolling();
          streamFallbackActive = false;
        }
      };

      stream.onerror = () => {
        setStreamStatus('fallback');
        closeStream();
        if (!streamFallbackActive) {
          streamFallbackActive = true;
          startPolling();
        }
      };
    };

    const startPolling = () => {
      if (pollTimer) {
        return;
      }
      fetchLogs(true);
      pollTimer = window.setInterval(fetchLogs, 2000);
    };

    const stopPolling = () => {
      if (!pollTimer) {
        return;
      }
      window.clearInterval(pollTimer);
      pollTimer = null;
    };

    const initialEnabled = toggles[0].dataset.provisioningEnabled === '1';
    applyState(initialEnabled, tail ? tail.querySelector('[data-log-line]') !== null : false);
    fetchLogs(true);
    connectStream();
    if (!window.EventSource || !streamEndpoint) {
      startPolling();
    }

    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        fetchLogs(true);
        if (!stream && streamEndpoint && window.EventSource) {
          connectStream();
        }
      }
    });
    window.addEventListener('beforeunload', () => {
      stopPolling();
      closeStream();
    }, { once: true });

    toggles.forEach((button) => {
      button.addEventListener('click', async () => {
        const next = button.dataset.provisioningEnabled !== '1';
        try {
          const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': token || '',
            },
            body: JSON.stringify({ enabled: next, _token: token || '' }),
          });
          if (!response.ok) {
            console.warn('Provisioning log toggle failed', response.status);
            return;
          }
          let payload = null;
          try {
            payload = await response.json();
          } catch (parseError) {
            console.warn('Provisioning log toggle returned non-JSON');
          }
          if (payload && typeof payload.enabled !== 'undefined') {
            applyState(!!payload.enabled, tail ? tail.querySelector('[data-log-line]') !== null : false);
          } else {
            applyState(next, tail ? tail.querySelector('[data-log-line]') !== null : false);
          }
          fetchLogs(true);
          if (next) {
            connectStream();
          } else if (!window.EventSource || !streamEndpoint) {
            startPolling();
          }
        } catch (error) {
          console.warn('Provisioning log toggle error', error);
        }
      });
    });
  };

  const setupGlobalLogoutButton = () => {
    const path = (window.location.pathname || '').toLowerCase();
    const authPaths = ['/auth/login', '/auth/forgot', '/auth/request-access', '/auth/request', '/auth/logout'];
    if (authPaths.some((authPath) => path.includes(authPath))) {
      return;
    }

    if (document.querySelector('[data-global-logout-button]')) {
      return;
    }

    const hasExistingLogout = Boolean(
      document.querySelector('a[href*="/auth/logout"], form[action*="/auth/logout"]')
    );
    if (hasExistingLogout) {
      return;
    }

    const logoutUrl = appBase ? `${appBase}/auth/logout` : '/auth/logout';
    const container = document.createElement('div');
    container.setAttribute('data-global-logout-button', 'true');
    container.style.position = 'fixed';
    container.style.right = '16px';
    container.style.bottom = '16px';
    container.style.zIndex = '9999';

    const logoutLink = document.createElement('a');
    logoutLink.href = logoutUrl;
    logoutLink.textContent = 'Logout';
    logoutLink.style.display = 'inline-flex';
    logoutLink.style.alignItems = 'center';
    logoutLink.style.justifyContent = 'center';
    logoutLink.style.height = '40px';
    logoutLink.style.minWidth = '104px';
    logoutLink.style.padding = '0 14px';
    logoutLink.style.borderRadius = '10px';
    logoutLink.style.border = '1px solid rgba(239, 68, 68, 0.35)';
    logoutLink.style.background = '#ffffff';
    logoutLink.style.color = '#dc2626';
    logoutLink.style.fontSize = '13px';
    logoutLink.style.fontWeight = '700';
    logoutLink.style.textDecoration = 'none';
    logoutLink.style.boxShadow = '0 10px 24px rgba(15, 23, 42, 0.14)';

    logoutLink.addEventListener('mouseenter', () => {
      logoutLink.style.background = '#fef2f2';
      logoutLink.style.borderColor = 'rgba(239, 68, 68, 0.5)';
    });
    logoutLink.addEventListener('mouseleave', () => {
      logoutLink.style.background = '#ffffff';
      logoutLink.style.borderColor = 'rgba(239, 68, 68, 0.35)';
    });

    container.appendChild(logoutLink);
    document.body.appendChild(container);
  };
  const init = () => {
    setupLoginFormEnhancements();
    setupOtpInputs();
    setupDeviceTypeFields();
    setupUserEditToggles();
    setupMultiSelectShortcuts();
    setupCheckboxShortcuts();
    setupCustomCommandPermissions();
    setupDevicePermissionPortInputs();
    setupDevicePermissionCommandInputs();
    setupGraphDeviceInterfaceInputs();
    setupDeviceEditToggles();
    setupDeviceEditTypeFields();
    setupDeviceRowLinks();
    setupAddDeviceToggle();
    setupCommandSelects();
    setupModalToggles();
    setupToastClose();
    setupCommandSlug();
    setupWizardFilters();
    setupLiveSearch();
    setupMobileTableScroll();
    setupCopyButtons();
    setupChips();
    setupPortalFilters();
    setupPortalNotifications();
    setupNotificationsMenu();
    setupSidebarToggle();
    setupProvisioningToggle();
    setupGlobalLogoutButton();
    setupBackupActions();
    setupStatusPolling();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

















