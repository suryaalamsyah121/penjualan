// ======================= KERANJANG =======================
let cart = [];
let currentRating = 0;

// Load cart dari localStorage
function loadCart() {
    try {
        let saved = localStorage.getItem('cart_bengkel');
        if (saved) {
            cart = JSON.parse(saved);
        } else {
            cart = [];
        }
    } catch(e) {
        console.log('Load cart error:', e);
        cart = [];
    }
    renderCart();
}

// Simpan cart ke localStorage
function saveCart() {
    try {
        localStorage.setItem('cart_bengkel', JSON.stringify(cart));
    } catch(e) {
        console.log('Save cart error:', e);
    }
    renderCart();
    loadRekomendasi();
}

// Tambah ke keranjang
function addToCart(id, nama, harga) {
    console.log('Add to cart:', id, nama, harga);
    
    let existing = cart.find(item => item.id == id);
    if (existing) {
        existing.qty++;
    } else {
        cart.push({ 
            id: id, 
            nama: nama, 
            harga: harga, 
            qty: 1 
        });
    }
    saveCart();
    showToast('✓ ' + nama + ' ditambahkan ke keranjang');
}

// Hapus dari keranjang
function removeFromCart(index) {
    if (index >= 0 && index < cart.length) {
        let item = cart[index];
        cart.splice(index, 1);
        saveCart();
        showToast('✗ ' + item.nama + ' dihapus dari keranjang');
    }
}

// Kosongkan keranjang
function clearCart() {
    if (confirm('Yakin ingin mengosongkan keranjang?')) {
        cart = [];
        saveCart();
        showToast('Keranjang dikosongkan');
    }
}

// Render keranjang
function renderCart() {
    let list = document.getElementById('cart-items');
    let totalSpan = document.getElementById('total-harga');
    
    if (!list) return;
    
    list.innerHTML = '';
    let total = 0;
    
    if (cart.length === 0) {
        list.innerHTML = '<li style="text-align:center; color:#888; padding:10px;">🛒 Keranjang kosong</li>';
    } else {
        cart.forEach((item, idx) => {
            let subtotal = item.harga * item.qty;
            total += subtotal;
            list.innerHTML += `
                <li style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #2a2a40;">
                    <div style="flex:2;">
                        <strong>${escapeHtml(item.nama)}</strong><br>
                        <small>Rp ${item.harga.toLocaleString()} x ${item.qty}</small>
                    </div>
                    <div style="flex:1; text-align: right;">
                        <strong>Rp ${subtotal.toLocaleString()}</strong><br>
                        <button onclick="removeFromCart(${idx})" style="background:#e94560; color:white; border:none; border-radius:50%; width:24px; height:24px; cursor:pointer; margin-top:5px;">✕</button>
                    </div>
                </li>
            `;
        });
    }
    totalSpan.innerText = total.toLocaleString();
}

// Toggle cart
function toggleCart() {
    let body = document.querySelector('.cart-body');
    if (body) {
        body.classList.toggle('collapsed');
    }
}

// ======================= CHECKOUT =======================
async function checkout() {
    if (cart.length === 0) {
        alert('❌ Keranjang masih kosong!');
        return;
    }
    
    let total = cart.reduce((sum, item) => sum + (item.harga * item.qty), 0);
    if (!confirm(`Total belanja: Rp ${total.toLocaleString()}\n\nYakin ingin checkout?`)) {
        return;
    }
    
    let btn = document.querySelector('.btn-checkout');
    let oldText = btn.innerHTML;
    btn.innerHTML = '⏳ Memproses...';
    btn.disabled = true;
    
    try {
        let response = await fetch('proses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'checkout', cart: cart })
        });
        
        let textResponse = await response.text();
        console.log('Checkout response:', textResponse);
        
        let data;
        try {
            data = JSON.parse(textResponse);
        } catch(e) {
            console.error('JSON parse error:', e);
            alert('Error: Server mengembalikan response tidak valid');
            return;
        }
        
        if (data.status === 'success') {
            // Kosongkan keranjang
            cart = [];
            saveCart();
            
            // Tampilkan modal rating
            showRatingModal(data.transaksi_id);
            showToast('✅ Checkout berhasil! Silakan beri rating');
        } else {
            alert('❌ Gagal checkout: ' + (data.message || 'Terjadi kesalahan'));
        }
    } catch (err) {
        console.error('Checkout error:', err);
        alert('Error: ' + err.message);
    } finally {
        btn.innerHTML = oldText;
        btn.disabled = false;
    }
}

// ======================= RATING MODAL =======================
function showRatingModal(transaksiId) {
    let modal = document.getElementById('ratingModal');
    if (!modal) {
        console.error('Modal rating tidak ditemukan');
        return;
    }
    
    document.getElementById('transaksi_id_field').value = transaksiId;
    
    // Reset stars
    for (let i = 1; i <= 5; i++) {
        let star = document.getElementById(`star-${i}`);
        if (star) {
            star.innerHTML = '☆';
            star.classList.remove('selected');
        }
    }
    
    let komenField = document.getElementById('komen_field');
    if (komenField) komenField.value = '';
    
    currentRating = 0;
    
    // Tampilkan modal
    modal.style.display = 'flex';
    modal.classList.add('active');
}

function closeRatingModal() {
    let modal = document.getElementById('ratingModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
    }
}

// Setup rating stars
function setupStars() {
    for (let i = 1; i <= 5; i++) {
        let star = document.getElementById(`star-${i}`);
        if (star) {
            // Hapus event listener lama dengan clone
            let newStar = star.cloneNode(true);
            star.parentNode.replaceChild(newStar, star);
            
            newStar.addEventListener('click', function() {
                currentRating = i;
                for (let j = 1; j <= 5; j++) {
                    let s = document.getElementById(`star-${j}`);
                    if (s) {
                        if (j <= currentRating) {
                            s.innerHTML = '★';
                            s.classList.add('selected');
                        } else {
                            s.innerHTML = '☆';
                            s.classList.remove('selected');
                        }
                    }
                }
            });
        }
    }
}

// Submit rating
async function submitRating() {
    let rating = currentRating;
    let komenField = document.getElementById('komen_field');
    let komen = komenField ? komenField.value.trim() : '';
    let transaksi_id = document.getElementById('transaksi_id_field').value;
    
    if (rating === 0) {
        alert('⭐ Silakan pilih rating bintang 1-5 terlebih dahulu!');
        return;
    }
    
    let btn = document.getElementById('submitRatingBtn');
    let oldText = btn ? btn.innerHTML : 'Kirim';
    if (btn) {
        btn.innerHTML = '⏳ Mengirim...';
        btn.disabled = true;
    }
    
    try {
        let response = await fetch('proses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'rating', 
                rating: rating, 
                komen: komen, 
                transaksi_id: transaksi_id 
            })
        });
        
        let textResponse = await response.text();
        console.log('Rating response:', textResponse);
        
        let data;
        try {
            data = JSON.parse(textResponse);
        } catch(e) {
            console.error('JSON parse error:', e);
            alert('Error: Server error');
            return;
        }
        
        if (data.status === 'success') {
            alert(`⭐ Terima kasih atas rating ${rating} bintang!`);
            closeRatingModal();
        } else {
            alert('❌ Gagal menyimpan rating: ' + (data.message || 'Terjadi kesalahan'));
        }
    } catch (err) {
        console.error('Rating error:', err);
        alert('Error: ' + err.message);
    } finally {
        if (btn) {
            btn.innerHTML = oldText;
            btn.disabled = false;
        }
    }
}

// ======================= SCAN BARCODE =======================
function scanBarcode() {
    let input = document.getElementById('barcode-input');
    if (!input) return;
    
    let code = input.value.trim();
    if (!code) {
        alert('Masukkan atau scan barcode terlebih dahulu');
        return;
    }
    
    fetch(`proses.php?action=cari_barcode&barcode=${encodeURIComponent(code)}`)
        .then(res => res.json())
        .then(produk => {
            if (produk && produk.id) {
                addToCart(produk.id, produk.nama, produk.harga);
                input.value = '';
                input.focus();
            } else {
                alert('❌ Produk dengan barcode "' + code + '" tidak ditemukan');
            }
        })
        .catch(err => {
            console.error('Barcode error:', err);
            alert('Gagal mencari produk');
        });
}

// ======================= FILTER KATEGORI =======================
function filterKategori(kategoriId, element) {
    // Update active class
    document.querySelectorAll('.kategori-list a').forEach(a => {
        a.classList.remove('active');
    });
    if (element) element.classList.add('active');
    
    let url = `proses.php?action=get_produk&kategori=${kategoriId}`;
    fetch(url)
        .then(res => res.json())
        .then(data => {
            let grid = document.getElementById('produk-list');
            if (!grid) return;
            
            if (data.length === 0) {
                grid.innerHTML = '<p style="text-align:center; padding:40px;">Tidak ada produk di kategori ini</p>';
                return;
            }
            
            grid.innerHTML = data.map(p => `
                <div class="card-produk">
                    <img src="uploads/${p.gambar || 'default.png'}" onerror="this.src='https://via.placeholder.com/200x140?text=No+Image'">
                    <div class="info">
                        <div class="nama">${escapeHtml(p.nama)}</div>
                        <div class="kategori">${escapeHtml(p.kategori_nama || '')}</div>
                        <div class="harga">Rp ${Number(p.harga).toLocaleString()}</div>
                        <div class="stok">📦 Stok: ${p.stok}</div>
                        <button onclick="addToCart(${p.id}, '${escapeJs(p.nama)}', ${p.harga})" ${p.stok <= 0 ? 'disabled' : ''}>
                            ${p.stok <= 0 ? '❌ Stok Habis' : '➕ Tambah'}
                        </button>
                    </div>
                </div>
            `).join('');
        })
        .catch(err => console.error('Filter error:', err));
}

// Helper functions
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function escapeJs(str) {
    if (!str) return '';
    return str.replace(/'/g, "\\'").replace(/"/g, '\\"');
}

// ======================= REKOMENDASI KNN =======================
async function loadRekomendasi() {
    let cartData = [];
    try {
        cartData = JSON.parse(localStorage.getItem('cart_bengkel') || '[]');
    } catch(e) {
        cartData = [];
    }
    
    let div = document.getElementById('rekomendasi-list');
    if (!div) return;
    
    if (cartData.length === 0) {
        div.innerHTML = '<div class="rekomendasi-item" style="color:#888;">Tambahkan produk ke keranjang untuk melihat rekomendasi KNN</div>';
        return;
    }
    
    div.innerHTML = '<div class="rekomendasi-item">⏳ Menghitung rekomendasi KNN...</div>';
    
    try {
        let res = await fetch('knn_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cart: cartData })
        });
        let data = await res.json();
        
        if (data && data.length > 0) {
            div.innerHTML = data.map(p => `
                <div class="rekomendasi-item" style="background:#0f0f1a; padding:12px; border-radius:12px; margin-bottom:10px;">
                    <img src="uploads/${p.gambar || 'default.png'}" onerror="this.src='https://via.placeholder.com/50x50?text=No+Image'" style="width:50px; height:50px; object-fit:cover; border-radius:8px; margin-bottom:8px;">
                    <div class="nama" style="font-weight:bold;">${escapeHtml(p.nama)}</div>
                    <div class="harga" style="color:#4ade80;">Rp ${Number(p.harga).toLocaleString()}</div>
                    <div class="skor" style="font-size:11px; color:#e94560;">Skor KNN: ${p.skor || '95'}%</div>
                    <button onclick="addToCart(${p.id}, '${escapeJs(p.nama)}', ${p.harga})" style="margin-top:8px; background:#e94560; border:none; padding:5px 12px; border-radius:8px; color:white; cursor:pointer;">Tambah</button>
                </div>
            `).join('');
        } else {
            div.innerHTML = '<div class="rekomendasi-item" style="color:#888;">Belum ada rekomendasi KNN untuk keranjang Anda</div>';
        }
    } catch (err) {
        console.error('Rekomendasi error:', err);
        div.innerHTML = '<div class="rekomendasi-item" style="color:#e53e3e;">Gagal memuat rekomendasi</div>';
    }
}

// ======================= TOAST NOTIFICATION =======================
function showToast(message) {
    let toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = message;
    toast.style.cssText = 'position:fixed; bottom:100px; right:30px; background:#4ade80; color:#1a1a2e; padding:12px 20px; border-radius:10px; z-index:1100; animation:slideIn 0.3s ease;';
    document.body.appendChild(toast);
    setTimeout(() => {
        if (toast && toast.remove) toast.remove();
    }, 2000);
}

// ======================= INITIALIZATION =======================
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded - initializing...');
    
    // Load cart
    loadCart();
    
    // Setup rating stars
    setupStars();
    
    // Setup event listeners
    let submitBtn = document.getElementById('submitRatingBtn');
    if (submitBtn) {
        submitBtn.addEventListener('click', submitRating);
    }
    
    let closeBtn = document.getElementById('closeModalBtn');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeRatingModal);
    }
    
    let clearCartBtn = document.querySelector('.clear-cart');
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', clearCart);
    }
    
    // Close modal on outside click
    window.onclick = function(e) {
        let modal = document.getElementById('ratingModal');
        if (e.target === modal) {
            closeRatingModal();
        }
    };
    
    // Enter key for barcode
    let barcodeInput = document.getElementById('barcode-input');
    if (barcodeInput) {
        barcodeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                scanBarcode();
            }
        });
    }
    
    console.log('Initialization complete');
});

// Tambahkan style untuk animasi toast jika belum ada
if (!document.querySelector('#toast-animation-style')) {
    let style = document.createElement('style');
    style.id = 'toast-animation-style';
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .toast {
            animation: slideIn 0.3s ease;
        }
    `;
    document.head.appendChild(style);
}