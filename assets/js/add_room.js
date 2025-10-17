// assets/js/add_room.js
document.addEventListener('DOMContentLoaded', () => {
  const form   = document.getElementById('addRoomForm');
  const result = document.getElementById('resultMessage');

  if (!form) {
    console.warn('[add_room] #addRoomForm not found on this page.');
    return;
  }
  if (!result) {
    console.warn('[add_room] #resultMessage not found; statuses will only log to console.');
  }

  // Change this if your endpoint is different:
  const ENDPOINT = 'api/add_room.php';   // or 'api/room_create.php'

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Basic client-side checks (optional)
    const titleEl = form.querySelector('[name="title"]');
    if (titleEl && !titleEl.value.trim()) {
      showResult('❌ Please enter a title.', false);
      return;
    }

    const submitBtn = form.querySelector('[type="submit"], #unitSubmit');
    const originalBtnText = submitBtn ? submitBtn.textContent : '';

    // Prepare form data
    const fd = new FormData(form);

    // UI: disable while sending
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Saving...';
    }
    showResult('', true); // clear

    try {
      const res = await fetch(ENDPOINT, {
        method: 'POST',
        body: fd,
        credentials: 'include' // send session cookie
        // DO NOT set Content-Type when sending FormData
      });

      // Try to parse JSON; if it fails, wrap as error
      const text = await res.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch {
        data = { success: false, message: text || 'Invalid server response' };
      }

      if (res.ok && data && data.success) {
        showResult('✅ Room added successfully!', true);
        form.reset();

    
        if (typeof window.sf_loadRooms === 'function') {
          try { await window.sf_loadRooms(); } catch {}
        }

        
        const modal = document.getElementById('unitModal');
        if (modal && modal.classList) {
          modal.classList.add('hidden');
          modal.classList.remove('flex');
        }
      } else {
        const msg = (data && data.message) ? data.message : `HTTP ${res.status}`;
        showResult('❌ Error: ' + msg, false);
      }
    } catch (err) {
      console.error('[add_room] Network/JS error:', err);
      showResult('❌ Network error. Please try again.', false);
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalBtnText || 'Save';
      }
    }
  });

  function showResult(message, ok) {
    if (!result) return;
    result.textContent = message;
    // Tailwind classes
    if (!message) {
      result.className = 'mt-2';
      return;
    }
    result.className = `mt-2 ${ok ? 'text-green-600' : 'text-red-600'}`;
  }
});
