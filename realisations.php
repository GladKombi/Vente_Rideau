<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Toutes nos réalisations - NGS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.65);
            --glass-border: rgba(255, 255, 255, 0.3);
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
            --card-bg: rgba(255, 255, 255, 0.75);
            --text-primary: #1a1a2e;
            --text-secondary: #4a4a6a;
            --text-muted: #6b7280;
            --accent-gradient: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        }

        .dark {
            --glass-bg: rgba(15, 23, 42, 0.7);
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            --card-bg: rgba(30, 41, 59, 0.65);
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --accent-gradient: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #f0f4ff 0%, #e8eeff 50%, #f5f3ff 100%);
            color: var(--text-primary);
        }

        .dark body {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
        }

        .glass {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
        }

        .premium-card {
            background: var(--card-bg);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid var(--glass-border);
            border-radius: 1rem;
        }

        .btn-glass {
            background: var(--accent-gradient);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-weight: 600;
        }

        .whatsapp-pulse {
            animation: whatsappPulse 2s infinite;
        }

        @keyframes whatsappPulse {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5)
            }

            50% {
                box-shadow: 0 0 0 12px rgba(34, 197, 94, 0)
            }
        }
    </style>
</head>

<body class="min-h-screen">
    <header class="sticky top-0 z-50 glass border-b border-white/10 p-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="index.php" class="text-[var(--text-primary)] font-bold text-xl"><i class="fas fa-arrow-left mr-2"></i>Retour à l'accueil</a>
            <button id="theme-toggle" class="w-12 h-6 rounded-full bg-gray-300 dark:bg-gray-600 relative"></button>
        </div>
    </header>

    <section class="py-16 md:py-24">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-extrabold mb-4">Toutes nos réalisations</h2>
                <div class="flex flex-wrap justify-center gap-2 mb-10" id="filtres-container">
                    <button class="filtre-btn active px-5 py-2.5 rounded-xl bg-gradient-to-r from-blue-900 to-blue-600 text-white text-sm font-semibold" data-categorie="tous">Tous</button>
                    <button class="filtre-btn px-5 py-2.5 rounded-xl glass text-sm font-medium text-[var(--text-secondary)]" data-categorie="rideaux">Rideaux</button>
                    <button class="filtre-btn px-5 py-2.5 rounded-xl glass text-sm font-medium text-[var(--text-secondary)]" data-categorie="voilages">Voilages</button>
                    <button class="filtre-btn px-5 py-2.5 rounded-xl glass text-sm font-medium text-[var(--text-secondary)]" data-categorie="stores">Stores</button>
                    <button class="filtre-btn px-5 py-2.5 rounded-xl glass text-sm font-medium text-[var(--text-secondary)]" data-categorie="installation">Installations</button>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6" id="realisations-grid">
                <div class="col-span-full flex justify-center py-16" id="loader">
                    <div class="w-10 h-10 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
                </div>
            </div>

            <div class="flex justify-center mt-10" id="pagination-buttons"></div>
        </div>
    </section>

    <div id="modal-realisation" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm hidden p-4">
        <div class="premium-card max-w-3xl w-full max-h-[85vh] overflow-y-auto" id="modal-content"></div>
    </div>

    <script>
        const API_BASE_URL = 'api/realisations.php';
        const WHATSAPP_NUMBER = '243977421421';
        let currentPage = 1;
        let currentCategorie = 'tous';
        let totalPages = 1;
        let sessionId = localStorage.getItem('ngs_session_id') || ('ngs_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9));
        localStorage.setItem('ngs_session_id', sessionId);

        const catLabels = {
            rideaux: 'Rideaux',
            voilages: 'Voilages',
            stores: 'Stores',
            installation: 'Installation',
            sur_mesure: 'Sur mesure',
            autre: 'Autre'
        };

        async function loadPage(page = 1, cat = 'tous') {
            const grid = document.getElementById('realisations-grid');
            const loader = document.getElementById('loader');
            grid.innerHTML = '';
            loader.classList.remove('hidden');
            try {
                let url = `${API_BASE_URL}?action=liste&page=${page}&limit=9&sort=recent`;
                if (cat !== 'tous') url += `&categorie=${cat}`;
                const resp = await fetch(url);
                const data = await resp.json();
                if (!data.success) throw new Error('Erreur');
                totalPages = data.pagination.total_pages;
                const realisations = data.data;
                if (realisations.length === 0) {
                    grid.innerHTML = `<div class="col-span-full text-center py-16 text-[var(--text-muted)]">Aucune réalisation</div>`;
                    return;
                }
                realisations.forEach(r => grid.appendChild(createCard(r)));
                updatePagination(page);
            } catch (e) {
                grid.innerHTML = `<div class="col-span-full text-center py-16 text-red-500">Erreur de chargement</div>`;
            } finally {
                loader.classList.add('hidden');
            }
        }

        function createCard(real) {
            const card = document.createElement('article');
            card.className = 'premium-card overflow-hidden group cursor-pointer';
            const date = new Date(real.date_realisation || real.date_creation).toLocaleDateString('fr-FR', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            const whatsappUrl = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(`🖼️ *${real.titre}*\n\nBonjour NGS ! Je suis intéressé(e) par cette réalisation.\n\n📌 Titre : ${real.titre}\n📂 Catégorie : ${catLabels[real.categorie]||real.categorie}\n${real.description?'📝 Description : '+real.description.substring(0,150)+'...\n':''}${real.client_ville?'📍 Lieu : '+real.client_ville+'\n':''}${real.prix_indicatif?'💰 Prix indicatif : $'+parseFloat(real.prix_indicatif).toFixed(2)+'\n':''}📅 Date : ${date}\n🏪 Boutique : ${real.boutique_nom||'NGS'}\n🔗 Réf : #${real.id}\n\n✨ Je souhaiterais un service similaire. Pouvez-vous me faire un devis ?\n\nMerci d'avance !`)}`;
            card.innerHTML = `
        <div class="relative h-52 sm:h-60 overflow-hidden">
          <img src="${real.image_principale}" alt="${real.titre}" class="w-full h-full object-cover" onerror="this.src='https://images.unsplash.com/photo-1618220179428-22790b461013?w=800'">
          <div class="absolute top-3 left-3"><span class="px-3 py-1 glass rounded-full text-xs font-medium">${catLabels[real.categorie]||real.categorie}</span></div>
          <button onclick="event.stopPropagation(); toggleLike(${real.id}, this)" class="like-btn absolute top-3 right-3 w-9 h-9 rounded-full bg-white/80 backdrop-blur-sm flex items-center justify-center"><i class="far fa-heart text-sm text-gray-600"></i><span class="absolute -bottom-8 right-0 bg-gray-900 text-white text-xs px-2 py-1 rounded opacity-0 group-hover/like:opacity-100"><span class="likes-count">${real.likes_count||0}</span> j'aime</span></button>
          ${real.images_count>1?`<div class="absolute bottom-3 right-3 bg-black/40 text-white text-xs px-2.5 py-1 rounded-full"><i class="fas fa-images mr-1"></i>${real.images_count}</div>`:''}
        </div>
        <div class="p-5">
          <div class="text-xs text-[var(--text-muted)] mb-2"><i class="fas fa-calendar-alt text-blue-500"></i> ${date} ${real.client_ville?'• '+real.client_ville:''}</div>
          <h3 class="font-bold mb-1 line-clamp-1">${real.titre}</h3>
          <p class="text-sm text-[var(--text-secondary)] mb-4 line-clamp-2">${real.description||'Réalisation sur mesure par nos experts.'}</p>
          <div class="flex items-center justify-between">
            <span class="text-xs text-[var(--text-muted)]"><i class="fas fa-store text-blue-500"></i> ${real.boutique_nom||'NGS'}</span>
            <div class="flex gap-2">
              <button onclick="event.stopPropagation(); openDetailModal(${real.id})" class="px-3 py-2 rounded-xl glass text-xs font-medium">Détails</button>
              <a href="${whatsappUrl}" target="_blank" onclick="event.stopPropagation();" class="px-3 py-2 rounded-xl bg-gradient-to-r from-green-500 to-green-600 text-white text-xs font-semibold whatsapp-pulse"><i class="fab fa-whatsapp"></i> Commander</a>
            </div>
          </div>
        </div>`;
            card.addEventListener('click', () => openDetailModal(real.id));
            return card;
        }

        async function toggleLike(id, btn) {
            const heart = btn.querySelector('i');
            const count = btn.querySelector('.likes-count');
            heart.style.transform = 'scale(1.3)';
            setTimeout(() => heart.style.transform = 'scale(1)', 200);
            const resp = await fetch(`${API_BASE_URL}?action=like`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    realisation_id: id,
                    session_id: sessionId
                })
            });
            const data = await resp.json();
            if (data.success) {
                if (data.liked) {
                    heart.classList.remove('far');
                    heart.classList.add('fas', 'text-red-500');
                } else {
                    heart.classList.remove('fas', 'text-red-500');
                    heart.classList.add('far');
                }
                if (count) count.textContent = data.likes_count;
            }
        }

        function updatePagination(page) {
            const container = document.getElementById('pagination-buttons');
            container.innerHTML = '';
            if (totalPages <= 1) return;
            const prev = document.createElement('button');
            prev.className = `w-10 h-10 rounded-xl glass flex items-center justify-center ${page===1?'opacity-40':''}`;
            prev.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prev.disabled = page === 1;
            if (page > 1) prev.onclick = () => {
                currentPage = page - 1;
                loadPage(currentPage, currentCategorie);
                window.scrollTo({
                    top: document.getElementById('realisations-grid').offsetTop - 100,
                    behavior: 'smooth'
                });
            };
            container.appendChild(prev);
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= page - 1 && i <= page + 1)) {
                    const btn = document.createElement('button');
                    btn.className = `w-10 h-10 rounded-xl text-sm font-medium ${i===page?'bg-gradient-to-r from-blue-900 to-blue-600 text-white shadow-md':'glass text-[var(--text-secondary)]'}`;
                    btn.textContent = i;
                    if (i !== page) btn.onclick = () => {
                        currentPage = i;
                        loadPage(currentPage, currentCategorie);
                        window.scrollTo({
                            top: document.getElementById('realisations-grid').offsetTop - 100,
                            behavior: 'smooth'
                        });
                    };
                    container.appendChild(btn);
                } else if (i === page - 2 || i === page + 2) {
                    const dots = document.createElement('span');
                    dots.className = 'px-2 text-[var(--text-muted)]';
                    dots.textContent = '...';
                    container.appendChild(dots);
                }
            }
            const next = document.createElement('button');
            next.className = `w-10 h-10 rounded-xl glass flex items-center justify-center ${page===totalPages?'opacity-40':''}`;
            next.innerHTML = '<i class="fas fa-chevron-right"></i>';
            next.disabled = page === totalPages;
            if (page < totalPages) next.onclick = () => {
                currentPage = page + 1;
                loadPage(currentPage, currentCategorie);
                window.scrollTo({
                    top: document.getElementById('realisations-grid').offsetTop - 100,
                    behavior: 'smooth'
                });
            };
            container.appendChild(next);
        }

        // Modal détail (similaire à l'index)
        async function openDetailModal(id) {
            const modal = document.getElementById('modal-realisation');
            const content = document.getElementById('modal-content');
            content.innerHTML = '<div class="flex justify-center items-center py-16"><div class="w-10 h-10 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div></div>';
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            try {
                const resp = await fetch(`${API_BASE_URL}?action=detail&id=${id}&session_id=${sessionId}`);
                const data = await resp.json();
                if (!data.success) throw new Error('Non trouvé');
                const real = data.data;
                const date = new Date(real.date_realisation || real.date_creation).toLocaleDateString('fr-FR', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                const whatsappUrl = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(`🖼️ *${real.titre}*\n\nBonjour NGS ! Je suis intéressé(e) par cette réalisation.\n\n📋 Détails :\n📌 ${real.titre}\n📂 ${catLabels[real.categorie]||real.categorie}\n${real.description?'📝 '+real.description.substring(0,150)+'...\n':''}${real.client_ville?'📍 '+real.client_ville+'\n':''}${real.prix_indicatif?'💰 $'+parseFloat(real.prix_indicatif).toFixed(2)+'\n':''}📅 ${date}\n🏪 ${real.boutique_nom||'NGS'}\n🔗 #${real.id}\n\n✨ Je souhaiterais un devis. Merci !`)}`;
                const allImages = [{
                    url: real.image_principale,
                    isMain: true
                }];
                if (real.galerie) real.galerie.forEach(img => allImages.push({
                    url: img.image_url,
                    isMain: false
                }));
                const hasMultiple = allImages.length > 1;
                const thumbs = hasMultiple ? `<div class="flex gap-2 overflow-x-auto pb-2 px-5 pt-4">${allImages.map((img,i)=>`<img src="${img.url}" class="thumbnail w-16 h-16 rounded-lg object-cover cursor-pointer border-2 ${i===0?'border-blue-600 opacity-100':'border-transparent opacity-70'}" onclick="changeMainImage(this,'${img.url}',${i})">`).join('')}</div>` : '';
                content.innerHTML = `
          <div class="relative">
            <div class="relative h-64 sm:h-80 overflow-hidden rounded-t-2xl">
              <img src="${real.image_principale}" alt="${real.titre}" class="w-full h-full object-cover" id="main-detail-image" onerror="this.src='https://images.unsplash.com/photo-1618220179428-22790b461013?w=1200'">
              ${hasMultiple?`<button onclick="navigateImage(-1)" class="absolute left-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/80 backdrop-blur-sm flex items-center justify-center"><i class="fas fa-chevron-left"></i></button><button onclick="navigateImage(1)" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/80 backdrop-blur-sm flex items-center justify-center"><i class="fas fa-chevron-right"></i></button><div class="absolute bottom-3 right-3 bg-black/50 text-white text-xs px-3 py-1.5 rounded-full"><span id="image-counter">1</span>/${allImages.length}</div>`:''}
              <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
              <button onclick="closeDetailModal()" class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/80 flex items-center justify-center"><i class="fas fa-times"></i></button>
              <div class="absolute bottom-5 left-5 right-5"><span class="inline-block px-3 py-1 glass rounded-full text-xs text-white mb-3">${catLabels[real.categorie]||real.categorie}</span><h2 class="text-xl font-bold text-white">${real.titre}</h2></div>
            </div>
            ${thumbs}
            <div class="p-5 sm:p-6 space-y-5">
              <div><h3 class="font-bold">✨ Description</h3><p class="text-sm text-[var(--text-secondary)]">${real.description||'Aucune description.'}</p></div>
              <div class="flex flex-wrap gap-3"><div class="flex items-center gap-2 px-3 py-2 glass rounded-xl text-sm"><i class="fas fa-calendar-check text-blue-500"></i> ${date}</div>${real.client_ville?`<div class="flex items-center gap-2 px-3 py-2 glass rounded-xl text-sm"><i class="fas fa-map-marker-alt text-blue-500"></i> ${real.client_ville}</div>`:''}${real.prix_indicatif?`<div class="flex items-center gap-2 px-3 py-2 glass rounded-xl text-sm"><i class="fas fa-tag text-blue-500"></i> $${parseFloat(real.prix_indicatif).toFixed(2)}</div>`:''}</div>
              ${real.client_nom?`<div class="p-4 rounded-xl bg-blue-50/50 dark:bg-blue-900/20 border border-blue-100"><p class="text-sm"><i class="fas fa-user-check text-blue-500 mr-2"></i>Client : ${real.client_nom}</p></div>`:''}
              <div class="p-5 rounded-xl bg-gradient-to-r from-green-500 to-green-600 text-white"><div class="flex items-center gap-3 mb-3"><i class="fab fa-whatsapp text-2xl"></i><h4 class="font-bold">Commander ce service</h4></div><p class="text-sm text-green-100 mb-4">Partagez cette réalisation sur WhatsApp.</p><a href="${whatsappUrl}" target="_blank" class="flex items-center justify-center gap-2 w-full py-3 bg-white text-green-600 rounded-xl font-bold"><i class="fab fa-whatsapp text-lg"></i> Partager sur WhatsApp</a></div>
              <div class="flex items-center justify-between pt-2"><div class="flex items-center gap-2 text-sm text-[var(--text-muted)]"><div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-900 to-blue-600 flex items-center justify-center"><i class="fas fa-store text-white text-xs"></i></div><span>${real.boutique_nom||'NGS'}</span></div><button onclick="toggleLike(${real.id}, this)" class="flex items-center gap-2 px-4 py-2 rounded-xl glass text-sm"><i class="${real.liked_by_user?'fas text-red-500':'far'} fa-heart"></i> <span class="likes-count">${real.likes_count}</span> j'aime</button></div>
            </div>
          </div>`;
                window._currentImages = allImages;
                window._currentIndex = 0;
            } catch (e) {
                content.innerHTML = '<div class="p-8 text-center text-red-500">Erreur de chargement</div>';
            }
        }

        function changeMainImage(thumb, url, index) {
            const main = document.getElementById('main-detail-image');
            if (main) {
                main.style.opacity = '0';
                setTimeout(() => {
                    main.src = url;
                    main.style.opacity = '1';
                }, 150);
            }
            document.querySelectorAll('.thumbnail').forEach(t => {
                t.classList.remove('border-blue-600', 'opacity-100');
                t.classList.add('border-transparent', 'opacity-70');
            });
            thumb.classList.remove('border-transparent', 'opacity-70');
            thumb.classList.add('border-blue-600', 'opacity-100');
            window._currentIndex = index;
            const c = document.getElementById('image-counter');
            if (c) c.textContent = index + 1;
        }

        function navigateImage(dir) {
            const imgs = window._currentImages;
            if (!imgs || imgs.length <= 1) return;
            let idx = (window._currentIndex || 0) + dir;
            if (idx < 0) idx = imgs.length - 1;
            if (idx >= imgs.length) idx = 0;
            window._currentIndex = idx;
            const main = document.getElementById('main-detail-image');
            if (main) {
                main.style.opacity = '0';
                setTimeout(() => {
                    main.src = imgs[idx].url;
                    main.style.opacity = '1';
                }, 150);
            }
            document.querySelectorAll('.thumbnail').forEach((t, i) => {
                if (i === idx) {
                    t.classList.remove('border-transparent', 'opacity-70');
                    t.classList.add('border-blue-600', 'opacity-100');
                } else {
                    t.classList.remove('border-blue-600', 'opacity-100');
                    t.classList.add('border-transparent', 'opacity-70');
                }
            });
            const c = document.getElementById('image-counter');
            if (c) c.textContent = idx + 1;
        }

        function closeDetailModal() {
            const modal = document.getElementById('modal-realisation');
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
        document.addEventListener('keydown', e => {
            const modal = document.getElementById('modal-realisation');
            if (modal && !modal.classList.contains('hidden')) {
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    navigateImage(-1);
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    navigateImage(1);
                }
            }
        });
        document.getElementById('modal-realisation').addEventListener('click', function(e) {
            if (e.target === this) closeDetailModal();
        });

        // Filtres
        document.querySelectorAll('.filtre-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filtre-btn').forEach(b => {
                    b.classList.remove('active', 'bg-gradient-to-r', 'from-blue-900', 'to-blue-600', 'text-white');
                    b.classList.add('glass', 'text-[var(--text-secondary)]');
                });
                this.classList.add('active', 'bg-gradient-to-r', 'from-blue-900', 'to-blue-600', 'text-white');
                this.classList.remove('glass', 'text-[var(--text-secondary)]');
                currentCategorie = this.dataset.categorie;
                currentPage = 1;
                loadPage(1, currentCategorie);
            });
        });

        // Theme toggle (simple)
        const themeToggle = document.getElementById('theme-toggle');
        const html = document.documentElement;
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme:dark)').matches)) html.classList.add('dark');
        themeToggle.addEventListener('click', () => {
            html.classList.toggle('dark');
            localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
        });

        document.addEventListener('DOMContentLoaded', () => loadPage(1, 'tous'));
    </script>
</body>

</html>