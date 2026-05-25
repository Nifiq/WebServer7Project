document.addEventListener('DOMContentLoaded', () => {
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
    nav
      .querySelectorAll('.nav__item--dropdown.open')
      .forEach((item) => item.classList.remove('open'));
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
});

document.addEventListener('DOMContentLoaded', () => {
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

    if (nextButton) {
      showCard((current + 1) % cards.length);
    }

    if (previousButton) {
      showCard((current - 1 + cards.length) % cards.length);
    }
  });
});

// =====================================================
// BACKEND FORM INTEGRATION
// =====================================================
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('.webform-form');
  if (!form) return;

  const messageBox = form.querySelector('.webform-message') || document.createElement('div');
  const submitButton = form.querySelector('.webform-button');
  const csrfInput = form.querySelector('input[name="csrf_token"]');
  const defaultButtonText = submitButton ? submitButton.textContent : '';

  const makeProjectUrl = (path) => new URL(path, window.location.href).toString();

  if (!messageBox.classList.contains('webform-message')) {
    messageBox.className = 'webform-message';
    messageBox.setAttribute('aria-live', 'polite');
    messageBox.hidden = true;
    form.prepend(messageBox);
  }

  const showMessage = (type, text) => {
    messageBox.hidden = false;
    messageBox.textContent = text;
    messageBox.classList.remove('webform-message--success', 'webform-message--error');
    messageBox.classList.add(type === 'success' ? 'webform-message--success' : 'webform-message--error');
  };

  const clearFieldErrors = () => {
    form.querySelectorAll('.webform-field-error').forEach((field) => {
      field.classList.remove('webform-field-error');
    });

    form.querySelectorAll('.webform-error-text').forEach((errorText) => {
      errorText.remove();
    });
  };

  const escapeSelector = (value) => {
    if (window.CSS && typeof window.CSS.escape === 'function') {
      return window.CSS.escape(value);
    }

    return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  };

  const showFieldErrors = (errors = {}) => {
    Object.entries(errors).forEach(([fieldName, errorText]) => {
      const hint = document.createElement('div');
      hint.className = 'webform-error-text';
      hint.textContent = errorText;

      if (fieldName === 'recaptcha') {
        const recaptchaBox = form.querySelector('.g-recaptcha');

        if (recaptchaBox) {
          recaptchaBox.insertAdjacentElement('afterend', hint);
        } else {
          showMessage('error', errorText);
        }

        return;
      }

      const field = form.querySelector(`[name="${escapeSelector(fieldName)}"]`);
      if (!field) return;

      field.classList.add('webform-field-error');

      const label = field.closest('.webform-checkbox');
      if (label) {
        label.insertAdjacentElement('afterend', hint);
      } else {
        field.insertAdjacentElement('afterend', hint);
      }
    });
  };

  const getTrimmedValue = (fieldName) => {
    const field = form.querySelector(`[name="${escapeSelector(fieldName)}"]`);
    return field ? field.value.trim() : '';
  };

  const validateFormBeforeSubmit = () => {
    const errors = {};

    const name = getTrimmedValue('name');
    const phone = getTrimmedValue('phone');
    const email = getTrimmedValue('email');
    const message = getTrimmedValue('message');
    const consent = form.querySelector('[name="consent"]');

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
  };

  const focusFirstInvalidField = (errors = {}) => {
    const firstFieldName = Object.keys(errors).find((fieldName) => fieldName !== 'recaptcha');
    if (!firstFieldName) return;

    const firstField = form.querySelector(`[name="${escapeSelector(firstFieldName)}"]`);
    if (!firstField) return;

    firstField.focus({ preventScroll: true });
    firstField.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };

  const readJsonResponse = async (response) => {
    const contentType = response.headers.get('content-type') || '';

    if (contentType.includes('application/json')) {
      return response.json();
    }

    const text = await response.text();
    throw new Error(
      `Сервер вернул не JSON. HTTP ${response.status}. Ответ: ${text.slice(0, 220)}`
    );
  };

  const loadCsrfToken = async () => {
    if (!csrfInput) return;

    try {
      const response = await fetch(makeProjectUrl('backend/csrf.php'), {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
      });

      const data = await readJsonResponse(response);

      if (data.ok && data.csrf_token) {
        csrfInput.value = data.csrf_token;
      }
    } catch (error) {
      console.error('Не удалось получить CSRF-токен:', error);
      showMessage('error', 'Не удалось подготовить форму к отправке. Проверьте файл backend/csrf.php на сервере.');
    }
  };

  loadCsrfToken();

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearFieldErrors();
    messageBox.hidden = true;

    const localErrors = validateFormBeforeSubmit();
    if (Object.keys(localErrors).length > 0) {
      showMessage('error', 'Заполните обязательные поля формы.');
      showFieldErrors(localErrors);
      focusFirstInvalidField(localErrors);
      return;
    }

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'ОТПРАВЛЯЕМ...';
    }

    try {
      const submitUrl = form.getAttribute('action') || 'backend/submit.php';
      const response = await fetch(makeProjectUrl(submitUrl), {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
      });

      const data = await readJsonResponse(response);

      if (!response.ok || !data.ok) {
        showMessage('error', data.message || 'Не удалось отправить форму.');
        showFieldErrors(data.errors || {});
        return;
      }

      showMessage('success', data.message || 'Спасибо! Заявка отправлена.');
      form.reset();

      if (csrfInput && data.csrf_token) {
        csrfInput.value = data.csrf_token;
      } else {
        await loadCsrfToken();
      }

      if (window.grecaptcha && typeof window.grecaptcha.reset === 'function') {
        window.grecaptcha.reset();
      }
    } catch (error) {
      console.error(error);
      showMessage(
        'error',
        'Не удалось отправить форму. Проверьте соединение и попробуйте ещё раз.'
      );
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = defaultButtonText;
      }
    }
  });
});
