

(function () {
  const wybor = document.getElementById('wybor');
  if (!wybor) return;
  const usluga = document.getElementById('f-usluga');
  const pracownik = document.getElementById('f-pracownik');
  const data = document.getElementById('f-data');
  const doby = document.getElementById('f-doby');


  usluga.addEventListener('change', () => {
    if (pracownik) pracownik.value = '';
    wybor.submit();
  });
  if (!pracownik || !data) return;

  const formularz = document.getElementById('rezerwuj');
  const pokaz = document.getElementById('pokaz');
  if (pokaz) pokaz.hidden = true;

  let numerZapytania = 0;

  function wyczysc(el) {
    while (el.firstChild) el.removeChild(el.firstChild);
  }

  function element(tag, klasa, tekst) {
    const el = document.createElement(tag);
    if (klasa) el.className = klasa;
    if (tekst !== undefined) el.textContent = tekst;
    return el;
  }

  function dobyPoprawne() {
    if (!doby) return true;
    const n = Number(doby.value);
    return Number.isInteger(n) && n >= Number(doby.min) && n <= Number(doby.max);
  }

  async function odswiez() {
    if (!usluga.value || !pracownik.value || !data.value || !dobyPoprawne()) return;
    const nr = ++numerZapytania;
    let adres = 'api-sloty.php?usluga=' + encodeURIComponent(usluga.value) +
      '&pracownik=' + encodeURIComponent(pracownik.value) + '&data=' + encodeURIComponent(data.value);
    if (doby) adres += '&doby=' + encodeURIComponent(doby.value);
    let odp;
    try {
      const r = await fetch(adres, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      odp = await r.json();
      if (!r.ok) odp = { sloty: [], komunikat: odp.blad || 'Nie udało się pobrać terminów.' };
    } catch (e) {
      odp = { sloty: [], komunikat: 'Nie udało się pobrać terminów. Spróbuj ponownie.' };
    }
    if (nr !== numerZapytania) return;


    const csrf = document.querySelector('input[name="csrf"]');
    const token = csrf ? csrf.value : '';
    wyczysc(formularz);
    formularz.hidden = false;
    formularz.method = 'post';
    formularz.action = 'rezerwacja.php';
    formularz.className = 'karta-formularz';
    const ukryte = { csrf: token, usluga: usluga.value, pracownik: pracownik.value, data: data.value };
    if (doby) ukryte.doby = doby.value;
    Object.keys(ukryte).forEach((k) => {
      const i = document.createElement('input');
      i.type = 'hidden'; i.name = k; i.value = ukryte[k];
      formularz.appendChild(i);
    });

    const wynajem = odp.typ === 'doby';
    if (wynajem && odp.zwrot_data) {
      const opis = element('p', 'podsumowanie');
      opis.id = 'podsumowanie';
      const nazwa = element('strong', '', usluga.options[usluga.selectedIndex].text.split(' – ')[0]);
      opis.append(nazwa, document.createElement('br'),
        'Odbiór: ' + data.value + ', zwrot: ' + odp.zwrot_data + ' o tej samej godzinie (' + odp.doba_txt + '). Razem: ');
      opis.appendChild(element('strong', '', odp.razem));
      formularz.appendChild(opis);
    }

    const pole = element('fieldset', 'sloty');
    pole.appendChild(element('legend', '', wynajem ? 'Wolne godziny odbioru' : 'Wolne godziny rozpoczęcia'));
    (odp.sloty || []).forEach((s, idx) => {
      const lab = element('label', 'slot');
      const inp = document.createElement('input');
      inp.type = 'radio'; inp.name = 'godzina'; inp.value = s; inp.required = true;
      lab.title = wynajem ? 'zwrot ' + odp.zwrot_data + ' o ' + s : 'do ' + (odp.koniec ? odp.koniec[idx] : '');
      lab.append(inp, element('span', '', s));
      pole.appendChild(lab);
    });
    if (!odp.sloty || !odp.sloty.length) pole.appendChild(element('p', 'muted', odp.komunikat || 'Brak wolnych terminów.'));
    formularz.appendChild(pole);
    if (odp.sloty && odp.sloty.length) {
      const wiersz = element('div', 'row');
      const et = element('label', '', 'Komentarz (opcjonalnie)');
      et.htmlFor = 'f-komentarz';
      const ta = document.createElement('textarea');
      ta.id = 'f-komentarz'; ta.name = 'komentarz'; ta.rows = 3; ta.maxLength = 500;
      wiersz.append(et, ta);
      formularz.appendChild(wiersz);
      const b = element('button', 'btn', 'Zarezerwuj');
      b.type = 'submit';
      formularz.appendChild(b);
    }
    const podp = document.getElementById('podpowiedz');
    if (podp) podp.hidden = true;
    let nowyAdres = 'rezerwacja.php?usluga=' + encodeURIComponent(usluga.value) +
      '&pracownik=' + encodeURIComponent(pracownik.value) + '&data=' + encodeURIComponent(data.value);
    if (doby) nowyAdres += '&doby=' + encodeURIComponent(doby.value);
    history.replaceState(null, '', nowyAdres);
  }

  pracownik.addEventListener('change', odswiez);
  data.addEventListener('change', odswiez);
  if (doby) doby.addEventListener('input', odswiez);
})();
