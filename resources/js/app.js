/*
 * JavaScript minimal (aucune dépendance) — compatible avec la CSP stricte (aucun script en ligne).
 *  - data-confirm : demande de confirmation avant les opérations sensibles ;
 *  - data-dialog-open / data-dialog-close : ouverture des boîtes de dialogue <dialog> ;
 *  - data-filtre-liste : filtre texte d'une liste de cases à cocher (mots dans n'importe quel ordre, sans accents) ;
 *  - data-choix-agents : choix d'agents dans une longue liste, par recherche côté serveur ;
 *  - data-tout-cocher : sélection groupée ;
 *  - état de chargement des boutons à la soumission (évite les doubles envois) ;
 *  - menu latéral : tiroir sur mobile, réduction en icônes sur bureau (préférence mémorisée) ;
 *  - affichage / masquage du mot de passe.
 */
document.addEventListener('submit', (e) => {
    const form = e.target;
    const msg = form.dataset.confirm || e.submitter?.dataset.confirm;
    if (msg && !window.confirm(msg)) {
        e.preventDefault();
        return;
    }
    // Formulaire qui renvoie un fichier (feuilles d'accès…) : la page ne change pas ; on bloque le bouton un moment
    // pour éviter un second clic, qui produirait de nouveaux mots de passe et rendrait le premier PDF caduc (2.3.2)
    if (form.hasAttribute('data-telechargement')) {
        const b = e.submitter || form.querySelector('button');
        if (b) {
            setTimeout(() => {
                b.disabled = true; b.dataset.texteInitial = b.textContent; b.textContent = 'Téléchargement en cours…';
                setTimeout(() => { b.disabled = false; b.textContent = b.dataset.texteInitial; }, 20000);
            }, 0);
        }
        return;
    }
    if (form.method.toLowerCase() === 'post' && !form.hasAttribute('data-sans-chargement')) {
        const b = e.submitter || form.querySelector('[type=submit]');
        if (b) {
            setTimeout(() => { b.disabled = true; b.dataset.texte = b.textContent; b.textContent = 'Traitement…'; }, 0);
        }
    }
});

document.addEventListener('click', (e) => {
    const o = e.target.closest('[data-dialog-open]');
    if (o) {
        document.getElementById(o.dataset.dialogOpen)?.showModal();
    }
    const c = e.target.closest('[data-dialog-close]');
    if (c) {
        c.closest('dialog')?.close();
    }
    if (e.target.closest('[data-menu-ouvrir]')) {
        document.body.classList.add('menu-ouvert');
        document.querySelector('#menu-lateral a, #menu-lateral button')?.focus();
    }
    if (e.target.closest('[data-menu-fermer]')) {
        document.body.classList.remove('menu-ouvert');
    }
    if (e.target.closest('[data-menu-reduire]')) {
        const reduit = document.documentElement.classList.toggle('menu-reduit');
        try { localStorage.setItem('inps.menuReduit', reduit ? '1' : '0'); } catch (err) { /* stockage indisponible */ }
    }
    // Afficher / masquer le mot de passe
    const b = e.target.closest('[data-basculer-mdp]');
    if (b) {
        const champ = document.getElementById(b.dataset.basculerMdp);
        const visible = champ.type === 'password';
        champ.type = visible ? 'text' : 'password';
        b.setAttribute('aria-pressed', visible ? 'true' : 'false');
        b.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        b.querySelector('.icone-afficher')?.classList.toggle('hidden', visible);
        b.querySelector('.icone-masquer')?.classList.toggle('hidden', !visible);
        champ.focus();
    }
    // Ferme le menu du compte lors d'un clic à l'extérieur
    document.querySelectorAll('details.menu-compte[open]').forEach((d) => { if (!d.contains(e.target)) d.removeAttribute('open'); });
});

// Préférence « menu réduit » (bureau)
try { if (localStorage.getItem('inps.menuReduit') === '1') document.documentElement.classList.add('menu-reduit'); } catch (err) { /* ignoré */ }

// Touche « / » : place le curseur dans la recherche générale (sauf pendant une saisie)
document.addEventListener('keydown', (e) => {
    if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
    const cible = e.target;
    if (cible.closest('input, textarea, select, [contenteditable]')) return;
    const champ = document.getElementById('recherche-globale');
    if (champ) { e.preventDefault(); champ.focus(); }
});

// Recherche générale : suggestions sous la barre pendant la frappe (28/10/2026), sans script dans la page (CSP).
// Les résultats viennent de la même route que la page (réponse JSON), donc avec les mêmes droits et le même cloisonnement.
(() => {
    const champ = document.querySelector('[data-suggestions]');
    const boite = document.getElementById('recherche-suggestions');
    if (!champ || !boite) return;
    let minuterie = null; let requete = null;
    const fermer = () => { boite.hidden = true; champ.setAttribute('aria-expanded', 'false'); };
    const liens = () => [...boite.querySelectorAll('a')];
    const element = (balise, classe, texte) => { const e = document.createElement(balise); if (classe) e.className = classe; if (texte) e.textContent = texte; return e; };
    const afficher = (donnees) => {
        boite.replaceChildren();
        if (!donnees.rubriques.length) {
            boite.append(element('div', 'vide', 'Aucun résultat pour « ' + donnees.q + ' ».'));
        }
        donnees.rubriques.forEach((r) => {
            boite.append(element('div', 'rubrique', r.titre));
            r.lignes.slice(0, 4).forEach((l) => {
                const a = element('a', null, l.titre); a.href = l.url; a.setAttribute('role', 'option');
                if (l.detail) a.append(element('span', 'detail', l.detail));
                boite.append(a);
            });
        });
        if (donnees.rubriques.length) {
            const tout = element('a', 'tout', 'Voir tous les résultats'); tout.href = donnees.page; tout.setAttribute('role', 'option'); boite.append(tout);
        }
        boite.hidden = false; champ.setAttribute('aria-expanded', 'true');
    };
    champ.addEventListener('input', () => {
        clearTimeout(minuterie);
        const q = champ.value.trim();
        if (q.length < 2) { fermer(); return; }
        minuterie = setTimeout(() => {
            requete?.abort(); requete = new AbortController();
            fetch(champ.dataset.suggestions + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, signal: requete.signal, credentials: 'same-origin' })
                .then((r) => (r.ok ? r.json() : null)).then((d) => { if (d && champ.value.trim() === q) afficher(d); })
                .catch(() => { /* requête annulée ou hors ligne : la touche Entrée reste disponible */ });
        }, 250);
    });
    champ.addEventListener('keydown', (e) => {
        if (boite.hidden || !['ArrowDown', 'ArrowUp', 'Enter'].includes(e.key)) return;
        const l = liens(); const i = l.findIndex((a) => a.getAttribute('aria-selected') === 'true');
        if (e.key === 'Enter') { if (i >= 0) { e.preventDefault(); window.location.href = l[i].href; } return; }
        e.preventDefault();
        const j = e.key === 'ArrowDown' ? (i + 1) % l.length : (i <= 0 ? l.length - 1 : i - 1);
        l.forEach((a, k) => a.setAttribute('aria-selected', k === j ? 'true' : 'false'));
        l[j]?.scrollIntoView({ block: 'nearest' });
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('.recherche-globale')) fermer(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') fermer(); });
})();

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.body.classList.remove('menu-ouvert');
    document.querySelectorAll('details.menu-compte[open]').forEach((d) => { d.removeAttribute('open'); d.querySelector('summary')?.focus(); });
});

// Texte comparable : minuscules, sans accents (« Sékou » = « sekou »)
const simplifier = (t) => t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

document.addEventListener('input', (e) => {
    const champ = e.target.closest('[data-filtre-liste]');
    if (!champ) return;
    // 29/10/2026 : chaque mot doit se retrouver, dans n'importe quel ordre (« oumar diarra » trouve « DIARRA Oumar »)
    const mots = simplifier(champ.value).split(/\s+/).filter(Boolean);
    let visibles = 0;
    document.querySelectorAll(champ.dataset.filtreListe + ' [data-filtre-texte]').forEach((el) => {
        const texte = simplifier(el.dataset.filtreTexte);
        const ok = mots.every((m) => texte.includes(m));
        el.classList.toggle('hidden', !ok);
        if (ok) visibles++;
    });
    // Masque un groupe (service) dont aucun agent ne correspond à la recherche
    document.querySelectorAll(champ.dataset.filtreListe + ' fieldset').forEach((g) => {
        g.classList.toggle('hidden', !g.querySelector('[data-filtre-texte]:not(.hidden)'));
    });
    document.querySelector(champ.dataset.filtreListe + ' [data-filtre-vide]')?.classList.toggle('hidden', visibles > 0);
});
// Nombre d'agents cochés dans une longue liste (29/10/2026)
document.addEventListener('change', (e) => {
    document.querySelectorAll('[data-compte-coches]').forEach((c) => {
        if (e.target.closest(c.dataset.compteCoches)) c.textContent = document.querySelectorAll(c.dataset.compteCoches + ' input[type=checkbox]:checked').length;
    });
});
// Entrée dans un champ de filtre : ne soumet pas le formulaire
document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.closest('[data-filtre-liste]')) e.preventDefault();
});

// Choix d'agents dans une longue liste (29/10/2026) : recherche dans tout le personnel côté serveur
// (route agents.choisir : mêmes droits que la page Personnel), puis ajout un par un sous forme d'étiquettes.
document.querySelectorAll('[data-choix-agents]').forEach((bloc) => {
    const champ = bloc.querySelector('[data-choix-recherche]');
    const liste = bloc.querySelector('[data-choix-resultats]');
    const etat = bloc.querySelector('[data-choix-etat]');
    const selection = bloc.querySelector('[data-choix-selection]');
    const aideInitiale = etat.textContent;
    let minuterie = null; let requete = null;
    const element = (balise, classe, texte) => { const x = document.createElement(balise); if (classe) x.className = classe; if (texte) x.textContent = texte; return x; };
    const choisis = () => new Set([...selection.querySelectorAll('[data-id]')].map((p) => p.dataset.id));
    const compter = () => {
        const n = selection.querySelectorAll('[data-id]').length;
        bloc.querySelector('[data-choix-compte]').textContent = n;
        selection.querySelector('[data-choix-aucun]').hidden = n > 0;
    };
    const fermer = () => { liste.hidden = true; champ.setAttribute('aria-expanded', 'false'); };
    const ajouter = (a) => {
        if (choisis().has(String(a.id))) return;
        const puce = element('span', 'choix-agents-puce', a.matricule + ' — ' + a.nom);
        puce.dataset.id = a.id;
        const cache = element('input'); cache.type = 'hidden'; cache.name = 'agents[]'; cache.value = a.id;
        const retirer = element('button', null, '×'); retirer.type = 'button'; retirer.dataset.choixRetirer = ''; retirer.setAttribute('aria-label', 'Retirer ' + a.nom);
        puce.append(cache, retirer);
        selection.insertBefore(puce, selection.querySelector('[data-choix-aucun]'));
        compter();
    };
    const afficher = (d, q) => {
        liste.replaceChildren();
        const deja = choisis();
        d.agents.forEach((a, i) => {
            const li = element('li', null, a.matricule + ' — ' + a.nom);
            li.setAttribute('role', 'option'); li.dataset.index = i;
            if (a.structure) li.append(element('span', 'detail', a.structure));
            if (deja.has(String(a.id))) { li.classList.add('deja'); li.append(element('span', 'detail', 'déjà choisi')); }
            li.addEventListener('mousedown', (e) => { e.preventDefault(); ajouter(a); li.classList.add('deja'); champ.focus(); });
            li._agent = a;
            liste.append(li);
        });
        etat.textContent = d.agents.length === 0 ? 'Aucun agent trouvé pour « ' + q + ' ».'
            : (d.plus ? 'Les 30 premiers résultats : précisez la recherche (prénom, matricule…) si l\'agent n\'y est pas.' : d.agents.length + ' agent(s) trouvé(s) : cliquez pour ajouter.');
        liste.hidden = d.agents.length === 0;
        champ.setAttribute('aria-expanded', d.agents.length ? 'true' : 'false');
    };
    champ.addEventListener('input', () => {
        clearTimeout(minuterie);
        const q = champ.value.trim();
        if (q.replace(/\s+/g, '').length < 2) { fermer(); etat.textContent = aideInitiale; return; }
        minuterie = setTimeout(() => {
            requete?.abort(); requete = new AbortController();
            const url = bloc.dataset.choixAgents + (bloc.dataset.choixAgents.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q);
            etat.textContent = 'Recherche…';
            fetch(url, { headers: { Accept: 'application/json' }, signal: requete.signal, credentials: 'same-origin' })
                .then((r) => (r.ok ? r.json() : Promise.reject(r))).then((d) => { if (champ.value.trim() === q) afficher(d, q); })
                .catch((err) => { if (err?.name !== 'AbortError') etat.textContent = 'La recherche n\'a pas abouti : réessayez.'; });
        }, 250);
    });
    champ.addEventListener('keydown', (e) => {
        const options = [...liste.querySelectorAll('li')];
        if (e.key === 'Enter') {
            e.preventDefault(); // ne soumet jamais le formulaire depuis la recherche
            if (liste.hidden || !options.length) return;
            const choisie = options.find((o) => o.getAttribute('aria-selected') === 'true') || (options.length === 1 ? options[0] : null);
            if (choisie) { ajouter(choisie._agent); choisie.classList.add('deja'); }
            return;
        }
        if (liste.hidden || !['ArrowDown', 'ArrowUp'].includes(e.key) || !options.length) return;
        e.preventDefault();
        const i = options.findIndex((o) => o.getAttribute('aria-selected') === 'true');
        const j = e.key === 'ArrowDown' ? (i + 1) % options.length : (i <= 0 ? options.length - 1 : i - 1);
        options.forEach((o, k) => o.setAttribute('aria-selected', k === j ? 'true' : 'false'));
        options[j].scrollIntoView({ block: 'nearest' });
    });
    champ.addEventListener('blur', () => setTimeout(fermer, 150));
    champ.addEventListener('focus', () => { if (liste.children.length && champ.value.trim().length >= 2) { liste.hidden = false; champ.setAttribute('aria-expanded', 'true'); } });
    selection.addEventListener('click', (e) => {
        const b = e.target.closest('[data-choix-retirer]');
        if (b) { b.closest('[data-id]').remove(); compter(); champ.focus(); }
    });
    bloc.addEventListener('keydown', (e) => { if (e.key === 'Escape') fermer(); });
});

// Demande de formation : conclusion du diagnostic (savoir-faire, moyens, temps) — même règle que BesoinFormation::conclusionDiagnostic
document.querySelectorAll('[data-diagnostic]').forEach((bloc) => {
    const boite = document.querySelector(bloc.dataset.diagnostic);
    if (!boite) return;
    const val = (n) => bloc.querySelector(`input[name="${n}"]:checked`)?.value;
    const maj = () => {
        const [s, m, t] = [val('diag_savoir'), val('diag_moyens'), val('diag_temps')];
        let classe = null; let texte = '';
        if (s === 'oui') { classe = 'message-erreur'; texte = 'Les agents savent déjà faire : une formation ne réglera probablement pas ce problème. Cherchez plutôt du côté des moyens, du temps ou de l\'organisation.'; }
        else if (s && m && t) {
            if (m === 'non' || t === 'non') { classe = 'message-alerte'; texte = 'Il manque du savoir-faire, mais aussi ' + (m === 'non' && t === 'non' ? 'des moyens et du temps' : (m === 'non' ? 'des moyens' : 'du temps')) + ' : la formation seule ne suffira pas.'; }
            else { classe = 'message-succes'; texte = 'Il manque du savoir-faire, et les agents ont les moyens et le temps : une formation est la bonne réponse.'; }
        }
        boite.hidden = !classe;
        boite.classList.remove('message-erreur', 'message-alerte', 'message-succes');
        if (classe) { boite.classList.add(classe); boite.querySelector('[data-texte]').textContent = texte; }
    };
    bloc.addEventListener('change', maj);
    maj();
});

// Compte utilisateur : personnalisation des droits (2.0.0)
document.querySelectorAll('[data-active-droits]').forEach((interrupteur) => {
    const liste = document.querySelector(interrupteur.dataset.activeDroits);
    if (!liste) return;
    const maj = () => liste.querySelectorAll('input[type=checkbox]').forEach((c) => { c.disabled = !interrupteur.checked || interrupteur.disabled; });
    interrupteur.addEventListener('change', maj);
    maj();
    // Changer de profil remet les droits par défaut de ce profil
    document.querySelectorAll('input[name=profil]').forEach((radio) => radio.addEventListener('change', () => {
        if (!radio.checked || radio.value === 'conserver') return;
        liste.querySelectorAll('input[data-profils]').forEach((c) => { c.checked = c.dataset.profils.split(' ').includes(radio.value); });
    }));
});

// Choix direction → service : la liste des services ne montre que ceux de la direction choisie
function filtrerServices(srv) {
    const dir = document.querySelector(srv.dataset.servicesDe);
    if (!dir) return;
    const d = dir.value;
    srv.querySelectorAll('optgroup').forEach((g) => {
        const visible = d === '' || g.dataset.direction === d;
        g.hidden = !visible;
        g.disabled = !visible;
    });
    const choisi = srv.selectedOptions[0];
    if (d !== '' && choisi && choisi.dataset.direction && choisi.dataset.direction !== d) srv.value = '';
}
document.querySelectorAll('select[data-services-de]').forEach((srv) => {
    filtrerServices(srv);
    document.querySelector(srv.dataset.servicesDe)?.addEventListener('change', () => filtrerServices(srv));
    // Choisir un service directement (sans direction) renseigne sa direction
    srv.addEventListener('change', () => {
        const dir = document.querySelector(srv.dataset.servicesDe);
        const o = srv.selectedOptions[0];
        if (dir && o && o.dataset.direction && dir.value !== o.dataset.direction) { dir.value = o.dataset.direction; filtrerServices(srv); }
    });
});

// Liste dont le choix recharge la page (ex. année du plan) : pas de script en ligne (CSP)
document.addEventListener('change', (e) => {
    const s = e.target.closest('select[data-soumission-auto]');
    if (s && s.form) s.form.requestSubmit();
});

document.addEventListener('change', (e) => {
    const t = e.target.closest('[data-tout-cocher]');
    if (!t) return;
    document.querySelectorAll(t.dataset.toutCocher).forEach((c) => {
        if (!c.closest('.hidden')) c.checked = t.checked;
    });
});

// Retour au navigateur : réactive les boutons
window.addEventListener('pageshow', () => {
    document.querySelectorAll('button[disabled][data-texte]').forEach((b) => { b.disabled = false; b.textContent = b.dataset.texte; });
});
