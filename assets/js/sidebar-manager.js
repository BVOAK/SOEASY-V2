/**
 * =============================================================================
 * SIDEBAR COLLAPSIBLE SYSTEM - SoEasy Configurateur
 * =============================================================================
 */

class SidebarManager {
  constructor() {
    this.isOpen = false;
    this.sidebar = null;
    this.toggleBtn = null;
    this.overlay = null;
    this.isMobile = window.innerWidth <= 991;
    
    this.init();
  }

  init() {
    // Attendre que le DOM soit prêt
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.setup());
    } else {
      this.setup();
    }
  }

  setup() {
    // Vérifier que la sidebar existe
    this.sidebar = document.querySelector('.sidebar');
    if (!this.sidebar) {
      console.warn('⚠️ Sidebar non trouvée');
      return;
    }

    // Créer les éléments nécessaires
    this.createToggleButton();
    this.overlay = this.createOverlay();
    
    // Charger l'état depuis localStorage
    this.loadState();
    
    // Event listeners
    this.bindEvents();
    
    console.log('✅ Sidebar Manager initialisé');
  }

  createToggleButton() {
    const toggleBtn = document.createElement('button');
    toggleBtn.className = 'sidebar-toggle';
    toggleBtn.setAttribute('aria-label', 'Toggle configuration sidebar');
    toggleBtn.setAttribute('title', 'Afficher/Masquer le récapitulatif');
    toggleBtn.innerHTML = '<i class="fas fa-shopping-cart"></i>';
    
    document.body.appendChild(toggleBtn);
    this.toggleBtn = toggleBtn;
  }

  createOverlay() {
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
    return overlay;
  }

  bindEvents() {
    // Toggle au clic du bouton
    this.toggleBtn.addEventListener('click', () => this.toggle());
    
    // Fermer avec l'overlay (mobile)
    this.overlay.addEventListener('click', () => this.close());
    
    // Fermer avec Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.isOpen) {
        this.close();
      }
    });

    // Gérer le resize
    window.addEventListener('resize', () => this.handleResize());

    // Bouton close dans la sidebar (si présent)
    document.addEventListener('click', (e) => {
      if (e.target.closest('.sidebar-close-btn')) {
        this.close();
      }
    });

    // Auto-ouvrir quand un produit est ajouté
    document.addEventListener('productAddedToConfig', () => {
      if (!this.isOpen && !this.isMobile) {
        this.open();
      }
    });
  }

  toggle() {
    if (this.isOpen) {
      this.close();
    } else {
      this.open();
    }
  }

  open() {
    if (this.isOpen) return;
    
    this.isOpen = true;
    
    // Ajouter les classes
    document.body.classList.add('sidebar-open');
    this.sidebar.classList.add('sidebar-visible');
    this.toggleBtn.classList.add('sidebar-open');
    
    // Changer l'icône
    const icon = this.toggleBtn.querySelector('i');
    if (icon) {
      icon.className = 'fas fa-times';
    }
    
    // Overlay pour mobile
    if (this.isMobile) {
      this.overlay.classList.add('active');
    }
    
    // Sauvegarder l'état
    this.saveState();
    
    // Event personnalisé
    document.dispatchEvent(new CustomEvent('sidebarOpened'));
    
    // Mettre à jour le recap si les fonctions existent
    setTimeout(() => {
      if (typeof updateSidebarProduitsRecap === 'function') {
        updateSidebarProduitsRecap();
      }
      if (typeof updateSidebarTotauxRecap === 'function') {
        updateSidebarTotauxRecap();
      }
    }, 100);
    
    console.log('📱 Sidebar ouverte');
  }

  close() {
    if (!this.isOpen) return;
    
    this.isOpen = false;
    
    // Retirer les classes
    document.body.classList.remove('sidebar-open');
    this.sidebar.classList.remove('sidebar-visible');
    this.toggleBtn.classList.remove('sidebar-open');
    this.overlay.classList.remove('active');
    
    // Remettre l'icône panier
    const icon = this.toggleBtn.querySelector('i');
    if (icon) {
      icon.className = 'fas fa-shopping-cart';
    }
    
    // Sauvegarder l'état
    this.saveState();
    
    // Event personnalisé
    document.dispatchEvent(new CustomEvent('sidebarClosed'));
    
    console.log('📱 Sidebar fermée');
  }

  handleResize() {
    const wasMobile = this.isMobile;
    this.isMobile = window.innerWidth <= 991;
    
    // Si on passe de mobile à desktop
    if (wasMobile && !this.isMobile) {
      this.overlay.classList.remove('active');
      if (!this.isOpen) {
        this.open(); // Auto-ouvrir sur desktop
      }
    }
    
    // Si on passe de desktop à mobile
    if (!wasMobile && this.isMobile) {
      if (this.isOpen) {
        this.overlay.classList.add('active');
      }
    }
  }

  saveState() {
    try {
      localStorage.setItem('soeasy_sidebar_state', JSON.stringify({
        isOpen: this.isOpen,
        timestamp: Date.now()
      }));
    } catch (e) {
      console.warn('Erreur sauvegarde état sidebar:', e);
    }
  }

  loadState() {
    try {
      const saved = localStorage.getItem('soeasy_sidebar_state');
      if (saved) {
        const state = JSON.parse(saved);
        
        // Auto-expire après 24h
        if (Date.now() - state.timestamp < 86400000) {
          if (state.isOpen && !this.isMobile) {
            this.open();
          }
          return;
        }
      }
    } catch (e) {
      console.warn('Erreur chargement état sidebar:', e);
    }
    
    // État par défaut : ouvert sur desktop si il y a déjà une config
    const config = localStorage.getItem('soeasyConfig');
    if (config && !this.isMobile) {
      try {
        const parsedConfig = JSON.parse(config);
        if (Object.keys(parsedConfig).length > 0) {
          this.open();
        }
      } catch (e) {
        // Config corrompue, on ignore
      }
    }
  }

  // API publique
  forceOpen() {
    this.open();
  }

  forceClose() {
    this.close();
  }

  getState() {
    return {
      isOpen: this.isOpen,
      isMobile: this.isMobile
    };
  }

  // Ajouter une notification sur le bouton
  addNotification(count = null) {
    let badge = this.toggleBtn.querySelector('.badge-notification');
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'badge-notification';
      this.toggleBtn.appendChild(badge);
    }
    
    if (count !== null) {
      badge.textContent = count > 99 ? '99+' : count;
      badge.style.display = count > 0 ? 'flex' : 'none';
    } else {
      badge.style.display = 'flex';
      badge.textContent = '!';
    }
  }

  removeNotification() {
    const badge = this.toggleBtn.querySelector('.badge-notification');
    if (badge) {
      badge.style.display = 'none';
    }
  }

  // Mettre à jour le compteur d'articles
  updateCartCount() {
    try {
      const config = JSON.parse(localStorage.getItem('soeasyConfig') || '{}');
      let totalItems = 0;
      
      Object.values(config).forEach(adresseConfig => {
        ['abonnements', 'materiels', 'fraisInstallation'].forEach(section => {
          if (Array.isArray(adresseConfig[section])) {
            totalItems += adresseConfig[section].length;
          }
        });
      });
      
      if (totalItems > 0) {
        this.addNotification(totalItems);
      } else {
        this.removeNotification();
      }
      
    } catch (e) {
      console.warn('Erreur mise à jour compteur panier:', e);
    }
  }
}

// Initialisation automatique
document.addEventListener('DOMContentLoaded', function() {
  // Attendre un peu que la sidebar soit injectée
  setTimeout(() => {
    if (document.querySelector('.sidebar')) {
      window.sidebarManager = new SidebarManager();
      
      // Mettre à jour le compteur au chargement
      if (window.sidebarManager.updateCartCount) {
        window.sidebarManager.updateCartCount();
      }
    }
  }, 200);
});

// Export pour usage externe
window.SidebarManager = SidebarManager;