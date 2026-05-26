// =====================================================
// MAIN UI: mobile menu + reviews + backend integration
// Подходит для страницы /katalog_zadaniya_8/
// =====================================================

(function () {
  'use strict';

  const onReady = (callback) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
    } else {
      callback();
    }
  };

  const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const makeProjectUrl = (path) => new URL(path, window.location.href).toString();

  function initMenu() {
    const burger = document.querySelector('.burger');
    const nav = document.querySelector('.nav');
    const body = document.body;

    if (!nav) return;

    const isDesktop = () => window.matchMedia('(min-width: 1024px)').matches;

    const setMenuState = (isOpen) => {
      nav.classList.toggle('nav--open', isOpen);
      body.classList.toggle('no-scroll', isOpen);

      if (burger) {
        burger.setAttribute('aria-expanded', String(isOpen));
        burger.setAttribute('aria-label', isOpen ? 'Закрыть меню' : 'Открыть меню');
      }
    };

    const closeAllDropdowns = () => {
      nav.querySelectorAll('.nav__item--dropdown.open').forEach((item) => {
        item.classList.remove('open');
      });
    };

    if (burger) {
      burger.setAttribute('type', 'button');
      burger.setAttribute('aria-controls', 'main-navigation');
      burger.setAttribute('aria-expanded', 'false');
      if (!nav.id) nav.id = 'main-navigation';

      burger.addEventListener('click', (event) => {
        event.preventDefault();
        setMenuState(!nav.classList.contains('nav--open'));
      });
    }

    nav.addEventListener('click', (event) => {
      const dropdownLink = event.target.closest('.nav__item--dropdown > .nav__link');
      const simpleLink = event.target.closest('.nav__item:not(.nav__item--dropdown) > .nav__link');

      if (dropdownLink) {
        event.preventDefault();
        event.stopPropagation();

        const item = dropdownLink.closest('.nav__item--dropdown');
        const shouldOpen = !item.classList.contains('open');
        closeAllDropdowns();
        item.classList.toggle('open', shouldOpen);
        return;
      }

      if (simpleLink && !isDesktop()) {
        setMenuState(false);
        closeAllDropdowns();
      }
    });

    document.addEventListener('click', (event) => {
      const clickInsideNav = nav.contains(event.target);
      const clickOnBurger = burger ? burger.contains(event.target) : false;
      if (!clickInsideNav && !clickOnBurger) {
        closeAllDropdowns();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      closeAllDropdowns();
      setMenuState(false);
    });

    window.addEventListener('resize', () => {
      if (isDesktop()) {
        closeAllDropdowns();
        setMenuState(false);
      }
    });
  }

  function initReviews() {
    const reviewsSection = document.querySelector('.reviews');
    if (!reviewsSection) return;

    const wrapper = reviewsSection.querySelector('.review-cards-wrapper');
    const cards = Array.from(reviewsSection.querySelectorAll('.review-card'));
    if (!wrapper || cards.length === 0) return;

    let current = cards.findIndex((card) => getComputedStyle(card).display !== 'none');
    if (current === -1) current = 0;

    const showCard = (index) => {
      cards.forEach((card, cardIndex) => {
        card.style.display = cardIndex === index ? 'block' : 'none';
      });
      current = index;
    };

    showCard(current);

    wrapper.addEventListener('click', (event) => {
      const nextButton = event.target.closest('.review-next');
      const previousButton = event.target.closest('.review-prev');

      if (!nextButton && !previousButton) return;
      event.preventDefault();

      if (nextButton) showCard((current + 1) % cards.length);
      if (previousButton) showCard((current - 1 + cards.length) % cards.length);
    });
  }

  function injectBackendStyles() {
    if (document.getElementById('backend-integration-styles')) return;

    const style = document.createElement('style');
    style.id = 'backend-integration-styles';
    style.textContent = `
      .webform-inner { position: relative; }
      .admin-panel-link {
        position: static;
        display: flex;
        align-items: center;
        justify-content: center;
        width: max-content;
        min-height: 36px;
        margin: 0 0 18px auto;
        padding: 8px 14px;
        border-radius: 999px;
        background: #2b2b2b;
        color: #fff;
        font-size: 13px;
        font-weight: 700;
        line-height: 1;
        text-decoration: none;
        box-shadow: 0 6px 18px rgba(0, 0, 0, .18);
      }
      .admin-panel-link:hover { background: #f28c00; color: #fff; }
      .webform-user-login {
        margin: 22px auto 0;
        max-width: 620px;
        padding: 20px;
        border-radius: 14px;
        background: rgba(255, 255, 255, .12);
        border: 1px solid rgba(255, 255, 255, .22);
        color: #fff;
      }
      .webform-user-login h3 {
        margin: 0 0 12px;
        font-size: 18px;
        line-height: 1.25;
      }
      .webform-user-login p {
        margin: 8px 0 14px;
        font-size: 14px;
        opacity: .9;
      }
      .webform-user-login-row {
        display: grid;
        grid-template-columns: 1fr 1fr auto;
        gap: 10px;
        align-items: center;
      }
      .webform-user-login input {
        width: 100%;
        box-sizing: border-box;
        border: 0;
        border-radius: 6px;
        padding: 14px 16px;
        font: inherit;
      }
      .webform-login-button,
      .webform-logout-button,
      .webform-copy-button {
        border: 0;
        border-radius: 6px;
        padding: 13px 18px;
        background: #f28c00;
        color: #fff;
        font-weight: 700;
        cursor: pointer;
      }
      .webform-login-status {
        margin-top: 12px;
        font-size: 14px;
      }
      .webform-login-status.is-error { color: #ffd2d2; }
      .webform-login-status.is-success { color: #d7ffd7; }
      .webform-message--success,
      .webform-message--error {
        display: block;
        box-sizing: border-box;
        width: 100%;
        margin: 0 0 16px;
        padding: 16px;
        border-radius: 12px;
        text-align: left;
        line-height: 1.45;
      }
      .webform-message--success { background: #ecfdf5; color: #064e3b; border: 1px solid #bbf7d0; }
      .webform-message--error { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }
      .webform-auth-data {
        display: grid;
        gap: 10px;
        margin-top: 12px;
      }
      .webform-auth-row {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
        padding: 10px;
        border-radius: 10px;
        background: rgba(255, 255, 255, .75);
      }
      .webform-auth-value {
        font-family: Consolas, Monaco, monospace;
        font-weight: 700;
        word-break: break-all;
      }
      .webform-copy-button {
        padding: 8px 10px;
        font-size: 12px;
        border-radius: 999px;
      }
      .webform-field-error { outline: 2px solid #e11d48 !important; }
      .webform-error-text {
        margin: -6px 0 10px;
        color: #fff3f3;
        font-size: 13px;
        line-height: 1.35;
      }
      .webform-message .webform-error-text { color: inherit; }
      .webform-editing-note {
        margin: 12px 0;
        padding: 12px 14px;
        border-radius: 10px;
        background: rgba(242, 140, 0, .16);
        color: #fff;
        font-size: 14px;
      }
      @media (max-width: 768px) {
        .admin-panel-link {
          position: static;
          margin: 0 auto 18px;
          width: max-content;
        }
        .webform-user-login-row { grid-template-columns: 1fr; }
      }
    `;
    document.head.appendChild(style);
  }

  function initBackendForm() {
    const form = document.querySelector('#support-form.webform-form, .webform-form');
    if (!form) return;

    injectBackendStyles();

    const webformInner = form.closest('.webform-inner') || form.parentElement;
    const messageBox = form.querySelector('.webform-message') || document.createElement('div');
    const submitButton = form.querySelector('.webform-button, [type="submit"]');
    const csrfInput = form.querySelector('input[name="csrf_token"]');
    const defaultButtonText = submitButton ? submitButton.textContent : 'СВЯЖИТЕСЬ С НАМИ';

    let editMode = false;
    let currentRequestId = null;

    // Автосохранение введённых данных формы в браузере.
    // Нужно, чтобы после перезагрузки страницы поля снова подставлялись.
    const draftStorageKey = 'katalog_zadaniya_8_support_form_draft_v1';
    const draftFieldNames = ['name', 'phone', 'email', 'message', 'consent'];
    let draftSaveTimer = null;
    let suppressDraftSave = false;

    function readDraftFromForm() {
      const draft = {};
      draftFieldNames.forEach((name) => {
        const field = getField(name);
        if (!field) return;
        draft[name] = field.type === 'checkbox' ? field.checked : field.value;
      });
      return draft;
    }

    function saveFormDraftNow() {
      if (suppressDraftSave) return;
      try {
        window.localStorage.setItem(draftStorageKey, JSON.stringify(readDraftFromForm()));
      } catch (error) {
        console.warn('Не удалось сохранить данные формы:', error);
      }
    }

    function scheduleFormDraftSave() {
      if (suppressDraftSave) return;
      window.clearTimeout(draftSaveTimer);
      draftSaveTimer = window.setTimeout(saveFormDraftNow, 150);
    }

    function restoreFormDraft() {
      let draft = null;

      try {
        const raw = window.localStorage.getItem(draftStorageKey);
        if (!raw) return;
        draft = JSON.parse(raw);
      } catch (error) {
        console.warn('Не удалось восстановить данные формы:', error);
        return;
      }

      if (!draft || typeof draft !== 'object') return;

      draftFieldNames.forEach((name) => {
        const field = getField(name);
        if (!field || !(name in draft)) return;
        if (field.type === 'checkbox') {
          field.checked = Boolean(draft[name]);
        } else {
          field.value = draft[name] || '';
        }
      });
    }

    function clearFormDraft() {
      try {
        window.localStorage.removeItem(draftStorageKey);
      } catch (error) {
        console.warn('Не удалось очистить сохранённые данные формы:', error);
      }
    }

    if (!messageBox.classList.contains('webform-message')) {
      messageBox.className = 'webform-message';
      messageBox.setAttribute('aria-live', 'polite');
      messageBox.hidden = true;
      form.prepend(messageBox);
    }

    function setCsrf(token) {
      if (csrfInput && token) csrfInput.value = token;
    }

    async function loadCsrf() {
      try {
        const response = await fetch(makeProjectUrl('backend/csrf.php'), {
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' }
        });
        const data = await response.json();
        if (data && data.csrf_token) setCsrf(data.csrf_token);
      } catch (error) {
        console.warn('Не удалось получить CSRF-токен:', error);
      }
    }

    function showMessage(type, html) {
      messageBox.hidden = false;
      messageBox.classList.remove('webform-message--success', 'webform-message--error');
      messageBox.classList.add(type === 'success' ? 'webform-message--success' : 'webform-message--error');
      messageBox.innerHTML = html;
    }

    function clearFieldErrors() {
      form.querySelectorAll('.webform-field-error').forEach((field) => {
        field.classList.remove('webform-field-error');
      });
      form.querySelectorAll('.webform-error-text').forEach((errorText) => {
        errorText.remove();
      });
    }

    function getField(name) {
      return form.querySelector(`[name="${CSS.escape(name)}"]`);
    }

    function getTrimmedValue(name) {
      const field = getField(name);
      return field ? field.value.trim() : '';
    }

    function showFieldErrors(errors = {}) {
      Object.entries(errors).forEach(([fieldName, errorText]) => {
        const field = getField(fieldName);
        if (!field) return;

        field.classList.add('webform-field-error');
        const hint = document.createElement('div');
        hint.className = 'webform-error-text';
        hint.textContent = errorText;

        const label = field.closest('.webform-checkbox');
        if (label) {
          label.insertAdjacentElement('afterend', hint);
        } else {
          field.insertAdjacentElement('afterend', hint);
        }
      });
    }

    function validateFormBeforeSubmit() {
      const errors = {};
      const name = getTrimmedValue('name');
      const phone = getTrimmedValue('phone');
      const email = getTrimmedValue('email');
      const message = getTrimmedValue('message');
      const consent = getField('consent');

      if (!name) {
        errors.name = 'Не заполнено поле «Ваше имя».';
      } else if (!/^[\p{L}\s-]{2,150}$/u.test(name)) {
        errors.name = 'Введите корректное имя: только буквы, пробелы и дефис.';
      }

      if (!phone) {
        errors.phone = 'Не заполнено поле «Телефон».';
      } else if (!/^\+?[0-9\s\-()]{7,25}$/.test(phone)) {
        errors.phone = 'Введите корректный телефон.';
      }

      if (!email) {
        errors.email = 'Не заполнено поле «E-mail».';
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errors.email = 'Введите корректный E-mail.';
      }

      if (message.length > 2000) {
        errors.message = 'Комментарий слишком длинный. Максимум 2000 символов.';
      }

      if (!consent || !consent.checked) {
        errors.consent = 'Поставьте галочку согласия на обработку персональных данных.';
      }

      return errors;
    }

    function focusFirstInvalidField(errors = {}) {
      const firstFieldName = Object.keys(errors)[0];
      if (!firstFieldName) return;

      const firstField = getField(firstFieldName);
      if (!firstField) return;

      firstField.focus({ preventScroll: true });
      firstField.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function initFormDraftStorage() {
      restoreFormDraft();

      draftFieldNames.forEach((name) => {
        const field = getField(name);
        if (!field) return;

        field.addEventListener('input', scheduleFormDraftSave);
        field.addEventListener('change', scheduleFormDraftSave);
      });
    }

    async function readJsonResponse(response) {
      const contentType = response.headers.get('content-type') || '';
      if (contentType.includes('application/json')) return response.json();

      const text = await response.text();
      throw new Error(`Сервер вернул не JSON. HTTP ${response.status}. Ответ: ${text.slice(0, 500)}`);
    }

    function renderAuthSuccess(data) {
      const requestId = data.request_id ? `#${escapeHtml(data.request_id)}` : '';
      const login = escapeHtml(data.login || '');
      const password = escapeHtml(data.password || '');

      return `
        <strong>${escapeHtml(data.message || 'Спасибо! Заявка отправлена.')}</strong>
        ${requestId ? `<div style="margin-top:8px;">ID заявки: <strong>${requestId}</strong></div>` : ''}
        <div class="webform-auth-data">
          <div><strong>Данные для входа:</strong></div>
          <div class="webform-auth-row">
            <span>Логин:</span>
            <span class="webform-auth-value" data-copy-value="${login}">${login}</span>
            <button type="button" class="webform-copy-button" data-copy-target="login">Скопировать</button>
          </div>
          <div class="webform-auth-row">
            <span>Пароль:</span>
            <span class="webform-auth-value" data-copy-value="${password}">${password}</span>
            <button type="button" class="webform-copy-button" data-copy-target="password">Скопировать</button>
          </div>
          <div><strong>Сохраните эти данные. Пароль показывается только один раз.</strong></div>
        </div>
      `;
    }

    function resetRecaptcha() {
      if (window.grecaptcha && typeof window.grecaptcha.reset === 'function') {
        try { window.grecaptcha.reset(); } catch (e) { /* ignore */ }
      }
    }

    function fillFormFromRequest(request) {
      if (!request) return;

      const fields = ['name', 'phone', 'email', 'message'];
      fields.forEach((name) => {
        const field = getField(name);
        if (field) field.value = request[name] || '';
      });

      const consent = getField('consent');
      if (consent) consent.checked = Boolean(Number(request.consent || 1));

      editMode = true;
      currentRequestId = request.id || null;

      if (submitButton) submitButton.textContent = 'СОХРАНИТЬ ИЗМЕНЕНИЯ';

      let note = form.querySelector('.webform-editing-note');
      if (!note) {
        note = document.createElement('div');
        note.className = 'webform-editing-note';
        form.insertBefore(note, submitButton || null);
      }
      note.textContent = `Вы редактируете заявку #${currentRequestId}. После изменения нажмите «Сохранить изменения».`;

      saveFormDraftNow();
      form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function resetEditMode() {
      editMode = false;
      currentRequestId = null;
      if (submitButton) submitButton.textContent = defaultButtonText;
      const note = form.querySelector('.webform-editing-note');
      if (note) note.remove();
    }

    function addAdminPanelLink() {
      if (!webformInner || webformInner.querySelector('.admin-panel-link')) return;

      const link = document.createElement('a');
      link.className = 'admin-panel-link';
      link.href = makeProjectUrl('backend/admin.php');
      link.textContent = 'Админ панель';
      if (messageBox && messageBox.parentElement === form) {
        form.insertBefore(link, messageBox.nextSibling);
      } else {
        form.insertBefore(link, form.firstElementChild);
      }
    }

    function addUserLoginBlock() {
      if (!webformInner || webformInner.querySelector('.webform-user-login')) return;

      const block = document.createElement('div');
      block.className = 'webform-user-login';
      block.innerHTML = `
        <h3>Вход для редактирования сохранённой заявки</h3>
        <p>Введите логин и пароль, которые появились после отправки формы.</p>
        <form class="webform-user-login-form" novalidate>
          <div class="webform-user-login-row">
            <input type="text" name="login" placeholder="Логин" autocomplete="username" required>
            <input type="password" name="password" placeholder="Пароль" autocomplete="current-password" required>
            <button type="submit" class="webform-login-button">Войти</button>
          </div>
          <div class="webform-login-status" aria-live="polite"></div>
        </form>
        <button type="button" class="webform-logout-button" hidden>Выйти из режима редактирования</button>
      `;

      form.insertAdjacentElement('afterend', block);

      const loginForm = block.querySelector('.webform-user-login-form');
      const status = block.querySelector('.webform-login-status');
      const logoutButton = block.querySelector('.webform-logout-button');

      const setStatus = (type, text) => {
        status.textContent = text || '';
        status.classList.toggle('is-error', type === 'error');
        status.classList.toggle('is-success', type === 'success');
      };

      loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        setStatus('', '');

        const login = loginForm.elements.login.value.trim();
        const password = loginForm.elements.password.value;

        if (!login || !password) {
          setStatus('error', 'Введите логин и пароль.');
          return;
        }

        const formData = new FormData();
        formData.set('login', login);
        formData.set('password', password);
        if (csrfInput) formData.set('csrf_token', csrfInput.value);

        const button = loginForm.querySelector('button[type="submit"]');
        const oldText = button.textContent;
        button.disabled = true;
        button.textContent = 'Вход...';

        try {
          const response = await fetch(makeProjectUrl('backend/user_login.php'), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
          });
          const data = await readJsonResponse(response);
          if (data.csrf_token) setCsrf(data.csrf_token);

          if (!response.ok || !data.ok) {
            setStatus('error', data.message || 'Неверный логин или пароль.');
            return;
          }

          fillFormFromRequest(data.request);
          setStatus('success', data.message || 'Вы вошли.');
          logoutButton.hidden = false;
        } catch (error) {
          setStatus('error', error.message || 'Ошибка входа.');
        } finally {
          button.disabled = false;
          button.textContent = oldText;
        }
      });

      logoutButton.addEventListener('click', async () => {
        try {
          const response = await fetch(makeProjectUrl('backend/user_logout.php'), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
          });
          const data = await readJsonResponse(response);
          if (data.csrf_token) setCsrf(data.csrf_token);
        } catch (error) {
          console.warn('Logout error:', error);
        }

        resetEditMode();
        clearFormDraft();
        logoutButton.hidden = true;
        setStatus('success', 'Вы вышли из режима редактирования.');
      });
    }

    messageBox.addEventListener('click', async (event) => {
      const copyButton = event.target.closest('.webform-copy-button');
      if (!copyButton) return;

      const row = copyButton.closest('.webform-auth-row');
      const value = row ? row.querySelector('.webform-auth-value')?.textContent : '';
      if (!value) return;

      try {
        await navigator.clipboard.writeText(value);
        const oldText = copyButton.textContent;
        copyButton.textContent = 'Скопировано';
        setTimeout(() => { copyButton.textContent = oldText; }, 1200);
      } catch (error) {
        console.warn('Copy failed:', error);
      }
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearFieldErrors();

      const clientErrors = validateFormBeforeSubmit();
      if (Object.keys(clientErrors).length > 0) {
        showFieldErrors(clientErrors);
        showMessage('error', 'Проверьте поля формы.');
        focusFirstInvalidField(clientErrors);
        return;
      }

      const formData = new FormData(form);
      const endpoint = editMode ? 'backend/user_update.php' : (form.getAttribute('action') || 'backend/submit.php');

      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = editMode ? 'СОХРАНЯЕМ...' : 'ОТПРАВЛЯЕМ...';
      }

      try {
        const response = await fetch(makeProjectUrl(endpoint), {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' }
        });

        const data = await readJsonResponse(response);
        if (data.csrf_token) setCsrf(data.csrf_token);

        if (!response.ok || !data.ok) {
          if (data.errors) {
            showFieldErrors(data.errors);
            focusFirstInvalidField(data.errors);
          }
          showMessage('error', escapeHtml(data.message || 'Не удалось отправить форму.'));
          resetRecaptcha();
          return;
        }

        if (editMode) {
          showMessage('success', escapeHtml(data.message || 'Заявка успешно обновлена.'));
          if (data.request) fillFormFromRequest(data.request);
        } else {
          showMessage('success', renderAuthSuccess(data));
          suppressDraftSave = true;
          form.reset();
          clearFormDraft();
          resetEditMode();
          window.setTimeout(() => { suppressDraftSave = false; }, 0);
        }

        resetRecaptcha();
      } catch (error) {
        showMessage('error', escapeHtml(error.message || 'Ошибка соединения с сервером.'));
        resetRecaptcha();
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = editMode ? 'СОХРАНИТЬ ИЗМЕНЕНИЯ' : defaultButtonText;
        }
      }
    });

    initFormDraftStorage();
    addAdminPanelLink();
    addUserLoginBlock();
    loadCsrf();
  }

  onReady(() => {
    initMenu();
    initReviews();
    initBackendForm();
  });
})();
