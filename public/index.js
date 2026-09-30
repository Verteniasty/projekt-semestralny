const menuBtn = document.querySelector('.menu-btn');
const menu = document.getElementById('menu');
if (menuBtn && menu) {
  menuBtn.addEventListener('click', () => {
    const open = menu.classList.toggle('open');
    menuBtn.setAttribute('aria-expanded', open);
  });
}

const form = document.getElementById('booking');
if (form) {
  const car = form.car, from = form.from, to = form.to;
  const totalEl = document.getElementById('total');
  const okEl = document.getElementById('ok');

  const today = new Date().toISOString().split('T')[0];
  from.min = today;
  to.min = today;

  const days = () => {
    if (!from.value || !to.value) return 0;
    const diff = (new Date(to.value) - new Date(from.value)) / 86400000;
    return diff >= 1 ? Math.round(diff) : 0;
  };

  const updateTotal = () => {
    const price = Number(car.selectedOptions[0]?.dataset.price || 0);
    const d = days();
    if (price && d) {
      totalEl.textContent = `${d} ${d === 1 ? 'doba' : 'dób'} × ${price} zł = ${d * price} zł`;
    } else {
      totalEl.textContent = 'Wybierz auto i daty, żeby zobaczyć cenę.';
    }
  };

  [car, from, to].forEach(el => el.addEventListener('change', updateTotal));
  from.addEventListener('change', () => { to.min = from.value || today; });

  document.querySelectorAll('[data-car]').forEach(btn => {
    btn.addEventListener('click', () => {
      car.value = btn.dataset.car;
      updateTotal();
    });
  });

  const setError = (field, msg) => {
    field.classList.toggle('invalid', Boolean(msg));
    form.querySelector(`[data-for="${field.name}"]`).textContent = msg;
  };

  form.addEventListener('submit', e => {
    e.preventDefault();
    okEl.hidden = true;
    let valid = true;

    const check = (field, msg) => {
      const bad = !field.value.trim() || (field.type === 'email' && !field.validity.valid);
      setError(field, bad ? msg : '');
      if (bad) valid = false;
    };

    check(car, 'Wybierz auto.');
    check(from, 'Podaj datę odbioru.');
    check(to, 'Podaj datę zwrotu.');
    if (from.value && to.value && days() === 0) {
      setError(to, 'Zwrot musi być co najmniej dzień po odbiorze.');
      valid = false;
    }
    check(form.elements['name'], 'Podaj imię i nazwisko.');
    check(form.email, 'Podaj poprawny adres e-mail.');

    if (!valid) return;

    okEl.textContent = `Dziękujemy, ${form.elements['name'].value.trim()}! Rezerwacja wysłana. Potwierdzenie przyjdzie na ${form.email.value.trim()}.`;
    okEl.hidden = false;
    form.reset();
    updateTotal();
  });
}

const garage = document.getElementById('garage');
if (garage) {
  const cards = [...garage.querySelectorAll('.card')];
  const chips = document.querySelectorAll('.chip');
  const sortSel = document.getElementById('sort');
  const empty = document.getElementById('empty');
  let filter = 'all';

  const render = () => {
    const dir = sortSel.value === 'asc' ? 1 : -1;
    cards.sort((a, b) => (a.dataset.price - b.dataset.price) * dir)
         .forEach(c => garage.appendChild(c));
    let shown = 0;
    cards.forEach(c => {
      const show = filter === 'all' || c.dataset.type === filter;
      c.hidden = !show;
      if (show) shown++;
    });
    empty.hidden = shown > 0;
  };

  chips.forEach(chip => chip.addEventListener('click', () => {
    chips.forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
    filter = chip.dataset.filter;
    render();
  }));
  sortSel.addEventListener('change', render);
  render();
}

const contact = document.getElementById('contact');
if (contact) {
  const ok = document.getElementById('contact-ok');
  contact.addEventListener('submit', e => {
    e.preventDefault();
    ok.hidden = true;
    let valid = true;
    const rules = { name: 'Podaj imię i nazwisko.', email: 'Podaj poprawny adres e-mail.', message: 'Napisz wiadomość.' };
    Object.keys(rules).forEach(key => {
      const field = contact.elements[key];
      const bad = !field.value.trim() || (field.type === 'email' && !field.validity.valid);
      field.classList.toggle('invalid', bad);
      contact.querySelector(`[data-for="${key}"]`).textContent = bad ? rules[key] : '';
      if (bad) valid = false;
    });
    if (!valid) return;
    ok.textContent = `Dziękujemy, ${contact.elements['name'].value.trim()}! Odpiszemy na ${contact.elements['email'].value.trim()}.`;
    ok.hidden = false;
    contact.reset();
  });
}