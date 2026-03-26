// ============================================
// 1. FLASH MESSAGES — auto hide after 3 seconds
// ============================================
setTimeout(function() {
    let alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.style.display = 'none';
    });
}, 3000);


// ============================================
// 2. CONFIRM BEFORE DELETING (admin panel)
// ============================================
document.querySelectorAll('.btn-delete').forEach(function(button) {
    button.addEventListener('click', function(e) {
        if(!confirm('Are you sure you want to delete this?')) {
            e.preventDefault();
        }
    });
});


// ============================================
// QUANTITY BUTTONS — works for multiple items
// ============================================
document.querySelectorAll('.qty-btn').forEach(function(button) {
    button.addEventListener('click', function() {
        let wrapper = this.closest('.qty-wrapper');
        let input   = wrapper.querySelector('.qty-input');
        let current = parseInt(input.value);
        let min     = parseInt(input.min) || 1;
        let max     = parseInt(input.max) || 99;

        if(this.dataset.action === 'minus' && current > min) {
            input.value = current - 1;
        }
        if(this.dataset.action === 'plus' && current < max) {
            input.value = current + 1;
        }
    });
});



// ============================================
// 4. SEARCH BAR — clear button
// ============================================
let searchInput = document.querySelector('.search-input');
let clearBtn    = document.querySelector('.search-clear');

if(searchInput && clearBtn) {
    searchInput.addEventListener('input', function() {
        clearBtn.style.display = this.value ? 'block' : 'none';
    });

    clearBtn.addEventListener('click', function() {
        searchInput.value = '';
        this.style.display = 'none';
        searchInput.focus();
    });
}


// ============================================
// 5. IMAGE PREVIEW BEFORE UPLOAD (admin only)
// ============================================
let imageInput   = document.getElementById('productImage');
let imagePreview = document.getElementById('imagePreview');

if(imageInput && imagePreview) {
    imageInput.addEventListener('change', function() {
        const file = this.files[0];
        if(file) {
            const reader = new FileReader();
            reader.onload = e => {
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });
}