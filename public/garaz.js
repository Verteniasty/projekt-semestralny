(() => {
  const lista = document.getElementById('flota');
  if (!lista) return;

  const dane = JSON.parse(document.getElementById('flota-dane').textContent);
  const karty = [...lista.querySelectorAll('.auto')];
  const chips = document.querySelectorAll('.chip');
  const suwak = document.getElementById('moc-min');
  const suwakWart = document.getElementById('moc-wart');
  const sortSel = document.getElementById('sort');
  const licznik = document.getElementById('licznik');
  const pusto = document.getElementById('pusto');
  let kategoria = 'all';

  const sortowanie = {
    'cena-asc': (a, b) => a.dataset.cena - b.dataset.cena,
    'cena-desc': (a, b) => b.dataset.cena - a.dataset.cena,
    'moc-desc': (a, b) => b.dataset.moc - a.dataset.moc,
  };

  function odswiez() {
    const minMoc = Number(suwak.value);
    suwakWart.textContent = minMoc;
    karty.sort(sortowanie[sortSel.value]).forEach(k => lista.appendChild(k));

    let widoczne = 0;
    karty.forEach(k => {
      const pasuje = (kategoria === 'all' || k.dataset.kat === kategoria)
                  && Number(k.dataset.moc) >= minMoc;
      k.hidden = !pasuje;
      if (pasuje) widoczne++;
    });
    licznik.textContent = `Pokazano ${widoczne} z ${karty.length} aut`;
    pusto.hidden = widoczne > 0;
  }

  chips.forEach(chip => chip.addEventListener('click', () => {
    chips.forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
    kategoria = chip.dataset.kat;
    odswiez();
  }));
  suwak.addEventListener('input', odswiez);
  sortSel.addEventListener('change', odswiez);
  odswiez();

  const modal = document.getElementById('auto-modal');
  const NAZWY_KAT = { suv: 'SUV', sedan: 'Sedan', coupe: 'Coupe', tuned: 'Tuned' };
  const el = id => document.getElementById(id);

  function otworz(slug) {
    const a = dane.find(x => x.slug === slug);
    if (!a) return;
    const moc = a.moc + (a.plus ? '+' : '');

    el('m-nazwa').textContent = a.nazwa;
    el('m-kat').textContent = NAZWY_KAT[a.kat] || '';
    el('m-opis').textContent = a.opis;
    el('m-cena').textContent = a.cena.toLocaleString('pl-PL') + ' zł';

    const media = el('m-media');
    media.replaceChildren();
    if (a.zdjecie) {
      const img = document.createElement('img');
      img.src = a.zdjecie;
      img.alt = a.nazwa;
      media.appendChild(img);
    } else {
      media.textContent = a.nazwa;
    }

    const spec = el('m-spec');
    spec.replaceChildren();
    [
      ['Rok', a.rok],
      ['Moc', moc + ' KM'],
      ['Silnik', a.silnik],
      ['Napęd', a.naped],
      ['0–100 km/h', String(a.przysp).replace('.', ',') + ' s'],
    ].forEach(([nazwa, wartosc]) => {
      const dt = document.createElement('dt');
      const dd = document.createElement('dd');
      dt.textContent = nazwa;
      dd.textContent = wartosc;
      spec.append(dt, dd);
    });

    document.body.style.overflow = 'hidden';
    modal.showModal();
  }

  lista.addEventListener('click', e => {
    if (e.target.closest('a')) return;
    const karta = e.target.closest('.auto');
    if (karta) otworz(karta.dataset.slug);
  });
  modal.addEventListener('click', e => { if (e.target === modal) modal.close(); });  
  modal.querySelector('.m-close').addEventListener('click', () => modal.close());
  modal.addEventListener('close', () => { document.body.style.overflow = ''; });
})();