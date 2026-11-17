
const menuBtn = document.querySelector('.menu-btn');
const menu = document.getElementById('menu');
if (menuBtn && menu) {
  menuBtn.addEventListener('click', () => {
    const open = menu.classList.toggle('open');
    menuBtn.setAttribute('aria-expanded', open);
  });
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


const typUslugi = document.getElementById('f-typ');
const czasWiersz = document.getElementById('czas-wiersz');
if (typUslugi && czasWiersz) {
  const przelacz = () => { czasWiersz.hidden = typUslugi.value === 'doby'; };
  typUslugi.addEventListener('change', przelacz);
  przelacz();
}
