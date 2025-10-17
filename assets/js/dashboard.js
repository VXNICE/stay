// final/assets/js/dashboard.js
(function(){
  'use strict';

  const PLACEHOLDER = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"><rect fill="#f3f4f6" width="100%" height="100%"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#9ca3af" font-size="20">No image</text></svg>'
  );

  function computeAppBase(){
    const baseEl = document.querySelector('base[href]');
    if(baseEl){
      let href = baseEl.getAttribute('href') || '/';
      if(!href.endsWith('/')) href += '/';
      return href;
    }
    const parts = location.pathname.split('/');
    if(parts.length > 1 && parts[1]) return `/${parts[1]}/`;
    return '/';
  }
  const APP_BASE = computeAppBase();

  function getImageUrl(raw){
    if(!raw) return PLACEHOLDER;
    raw = String(raw).trim();
    if(/^https?:\/\//i.test(raw)) return raw;
    if(raw.startsWith('/')) {
      if(APP_BASE !== '/' && raw.startsWith(APP_BASE)) return raw;
      return APP_BASE.replace(/\/$/,'') + raw;
    }
    return APP_BASE + raw.replace(/^\/+/, '');
  }

  document.addEventListener('DOMContentLoaded', () => {
    // Refs
    const grid = document.getElementById('units');
    const empty = document.getElementById('empty');
    const selLoc = document.getElementById('filterLocation');
    const minR = document.getElementById('filterMinPrice');
    const maxR = document.getElementById('filterMaxPrice');
    const minLbl = document.getElementById('minPriceLabel');
    const maxLbl = document.getElementById('maxPriceLabel');
    const addBtn = document.getElementById('addUnitBtn');

    const modal = document.getElementById('unitModal');
    const modalTitle = document.getElementById('unitModalTitle');
    const modalClose = document.getElementById('unitModalClose');
    const form = document.getElementById('unitForm');
    const cancelBtn = document.getElementById('unitCancel');
    const submitBtn = document.getElementById('unitSubmit');

    const viewModal = document.getElementById('viewModal');
    const viewClose = document.getElementById('viewClose');
    const viewCloseBtn = document.getElementById('viewCloseBtn');
    const viewImg = document.getElementById('viewImg');
    const viewTitle = document.getElementById('viewTitle');
    const viewLocation = document.getElementById('viewLocation');
    const viewPrice = document.getElementById('viewPrice');
    const viewCapacity = document.getElementById('viewCapacity');
    const viewDesc = document.getElementById('viewDesc');
    const viewLocationLinkWrap = document.getElementById('viewLocationLinkWrap');
    const viewLocationLink = document.getElementById('viewLocationLink');
    const viewBookBtn = document.getElementById('viewBookBtn');

    const bookingModal = document.getElementById('bookingModal');
    const bookingForm = document.getElementById('bookingForm');
    const bookingClose = document.getElementById('bookingClose');
    const bookingCancel = document.getElementById('bookingCancel');
    const bookingSubmit = document.getElementById('bookingSubmit');
    const bookingRoomId = document.getElementById('bookingRoomId');
    const bookingStart = document.getElementById('bookingStart');
    const bookingEnd = document.getElementById('bookingEnd');
    const bookingGuests = document.getElementById('bookingGuests');
    const bookingError = document.getElementById('bookingError');

    const paymentModal = document.getElementById('paymentModal');
    const paymentForm = document.getElementById('paymentForm');
    const paymentClose = document.getElementById('paymentClose');
    const paymentCancel = document.getElementById('paymentCancel');
    const paymentSubmit = document.getElementById('paymentSubmit');
    const paymentBookingId = document.getElementById('paymentBookingId');
    const paymentError = document.getElementById('paymentError');

    // Approvals
    const openApprovalsBtn = document.getElementById('openApprovalsBtn');
    const approvalsModal   = document.getElementById('approvalsModal');
    const approvalsClose   = document.getElementById('approvalsClose');
    const approvalsList    = document.getElementById('approvalsList');
    const approvalsEmpty   = document.getElementById('approvalsEmpty');
    const approvalsError   = document.getElementById('approvalsError');
    const approvalsBadge   = document.getElementById('approvalsBadge');

    // state
    let allRooms = [];
    let canManage = false;
    window.currentRoomId = null;

    const show = el => el && el.classList && el.classList.remove('hidden');
    const hide = el => el && el.classList && el.classList.add('hidden');
    const escHtml = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    const escAttr = s => String(s ?? '').replace(/"/g,'&quot;');

    // Logout
    document.getElementById('logoutBtn')?.addEventListener('click', async () => { try { await fetch('api/logout.php', { credentials:'include' }); } catch{} window.location.href = 'login.html'; });
    document.getElementById('logoutBtnMobile')?.addEventListener('click', async () => { try { await fetch('api/logout.php', { credentials:'include' }); } catch{} window.location.href = 'login.html'; });

    // Auth + load
    (async function init(){
      const r = await fetch('api/me.php', { credentials:'include' });
      const payload = await r.json().catch(()=>({}));
      const user = payload?.user || null;
      if (!user) {
        window.location.href = 'login.html';
        return;
      }
      // paint name/role
      const nameEl = document.getElementById('signedInName');
      const roleEl = document.getElementById('signedInRole');
      if (nameEl) nameEl.textContent = user.name?.trim() || user.email || 'User';
      if (roleEl) roleEl.textContent = user.role_name || '';

      canManage = ['admin','owner','manager'].includes((user.role_name||'').toLowerCase());
      if (!canManage && addBtn) {
        addBtn.setAttribute('disabled', 'true');
        addBtn.classList.add('opacity-60');
      } else if (canManage && openApprovalsBtn) {
        openApprovalsBtn.classList.remove('hidden');
        refreshApprovalsBadge();
        // Optional auto refresh
        setInterval(() => refreshApprovalsBadge(), 60000);
      }

      await loadRooms();
    })().catch(()=>{});

    async function loadRooms() {
      hide(empty); if (grid) grid.innerHTML = '';
      const q = new URLSearchParams({
        location: selLoc?.value || '',
        min: minR?.value || '',
        max: maxR?.value || ''
      });
      try {
        const res = await fetch(`api/rooms_list.php?${q}`, { credentials:'include' });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Failed to load rooms');
        allRooms = data.rooms || [];
        buildLocationFilter(allRooms);
        render();
      } catch (e) {
        if (empty) { empty.textContent = 'Failed to load rooms.'; show(empty); }
      }
    }

    function buildLocationFilter(rooms) {
      if (!selLoc) return;
      const unique = Array.from(new Set(rooms.map(r => (r.location || '').trim()).filter(Boolean))).sort((a,b)=>a.localeCompare(b));
      selLoc.innerHTML = '<option value="">All locations</option>' + unique.map(loc => `<option value="${escAttr(loc)}">${escHtml(loc)}</option>`).join('');
    }

    function render() {
      if (minLbl) minLbl.textContent = minR?.value ?? '';
      if (maxLbl) maxLbl.textContent = maxR?.value ?? '';

      const min = Number(minR?.value || 0);
      const max = Number(maxR?.value || Infinity);
      const loc = selLoc?.value || '';

      const list = allRooms.filter(r => {
        const price = r.price == null ? null : Number(r.price);
        const okLoc = loc === '' || (r.location || '') === loc;
        const okPrice = price == null || (price >= min && price <= max);
        return okLoc && okPrice;
      });

      if (!list.length) {
        if (grid) grid.innerHTML = '';
        if (empty) { empty.textContent = 'No units found.'; show(empty); }
        return;
      }
      hide(empty);

      grid.innerHTML = list.map(r => {
        const title = r.title || '(Untitled)';
        const loc = r.location || '';
        const price = r.price != null ? Number(r.price) : '';
        const cap = r.capacity != null ? r.capacity : '';
        const imgSrc = getImageUrl(r.image || '');
        const id = r.id;

        return `
          <div class="bg-white border rounded-xl overflow-hidden shadow-sm hover:shadow-md transition">
            ${imgSrc ? `<img src="${escAttr(imgSrc)}" data-original="${escAttr(imgSrc)}" alt="${escAttr(title)}" class="h-40 w-full object-cover" onerror="this.onerror=null; this.src='${escAttr(PLACEHOLDER)}';">` : `<div class="h-40 w-full bg-gray-200"></div>`}
            <div class="p-4">
              <h3 class="font-semibold text-gray-900">${escHtml(title)}</h3>
              <p class="text-sm text-gray-600">${escHtml(loc)}</p>
              <div class="mt-2 flex items-center justify-between text-sm text-gray-700">
                <span>${price !== '' ? '₱ ' + price.toLocaleString() : ''}</span>
                <span>${cap !== '' ? cap + ' pax' : ''}</span>
              </div>
              <div class="mt-3 grid gap-2 ${canManage ? 'grid-cols-3' : 'grid-cols-2'}">
                <button data-view="${id}" class="px-3 py-2 rounded bg-blue-700 text-white text-sm hover:bg-blue-800">View</button>
                ${canManage ? `
                  <button data-edit="${id}" class="px-3 py-2 rounded border text-sm hover:bg-gray-50">Edit</button>
                  <button data-del="${id}" class="px-3 py-2 rounded border text-sm hover:bg-red-50">Delete</button>
                ` : `<button data-book="${id}" class="px-3 py-2 rounded bg-green-600 text-white text-sm hover:bg-green-700">Book Now</button>`}
              </div>
            </div>
          </div>
        `;
      }).join('');

      grid.querySelectorAll('[data-view]').forEach(btn => btn.addEventListener('click', () => openView(btn.dataset.view)));
      grid.querySelectorAll('[data-book]').forEach(btn => btn.addEventListener('click', () => openBooking(btn.dataset.book)));
      if (canManage) {
        grid.querySelectorAll('[data-edit]').forEach(btn => btn.addEventListener('click', () => openEdit(btn.dataset.edit)));
        grid.querySelectorAll('[data-del]').forEach(btn => btn.addEventListener('click', () => doDelete(btn.dataset.del)));
      }
    }

    [selLoc, minR, maxR].forEach(el => el && el.addEventListener('input', render));
    addBtn?.addEventListener('click', () => openCreate());
    modalClose?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);
    modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    function openCreate(){
      if (!form) return;
      modalTitle.textContent = 'Add Unit';
      form.reset();
      form.id.value = '';
      form.existing_image_path.value = '';
      submitBtn.textContent = 'Create';
      modal.classList.remove('hidden'); modal.classList.add('flex');
    }

    function openEdit(id){
      const r = allRooms.find(x=>String(x.id)===String(id)); if(!r) return;
      modalTitle.textContent='Edit Unit'; form.reset();
      form.id.value=r.id||''; 
      form.title.value=r.title||''; 
      form.location.value=r.location||''; 
      form.capacity.value=r.capacity||''; 
      form.price_per_night.value=r.price ?? '';
      form.existing_image_path.value=r.image||''; 
      form.description.value=r.description||''; 
      try{ form.querySelector('[name="location_link"]').value=r.location_link||''; }catch(e){}
      submitBtn.textContent='Update'; 
      modal.classList.remove('hidden'); modal.classList.add('flex');
    }

    function closeModal(){ modal?.classList.add('hidden'); modal?.classList.remove('flex'); }

    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      submitBtn.disabled = true;
      const fd = new FormData(form);
      const isEdit = !!fd.get('id');
      const url = isEdit ? 'api/room_update.php' : 'api/room_create.php';
      try {
        const res = await fetch(url, { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (!data.success) throw new Error(data.message||'Save failed');
        closeModal(); await loadRooms();
      } catch (err) {
        alert(err.message||err);
      } finally {
        submitBtn.disabled = false;
      }
    });

    async function doDelete(id){
      if(!confirm('Delete this unit?')) return;
      try {
        const fd = new FormData(); fd.append('id', id);
        const res = await fetch('api/room_delete.php', { method:'POST', body:fd, credentials:'include' });
        const data = await res.json();
        if(!data.success) throw new Error(data.message||'Delete failed');
        await loadRooms();
      } catch(e){ alert(e.message||e); }
    }

    function openView(id){
      const r = allRooms.find(x=>String(x.id)===String(id)); if(!r) return;
      if(viewImg) viewImg.src = getImageUrl(r.image || '');
      viewTitle && (viewTitle.textContent = r.title || '(Untitled)');
      viewLocation && (viewLocation.textContent = r.location ? `📍 ${r.location}` : '');
      viewPrice && (viewPrice.textContent = (r.price != null) ? `₱ ${Number(r.price).toLocaleString()}` : '');
      viewCapacity && (viewCapacity.textContent = (r.capacity != null) ? `• ${r.capacity} pax` : '');
      viewDesc && (viewDesc.textContent = r.description || '');
      window.currentRoomId = r.id;
      if (viewModal) viewModal.dataset.roomId = r.id;
      if (r.location_link && /^https?:\/\//i.test(r.location_link)) { if(viewLocationLink){ viewLocationLink.href = r.location_link; show(viewLocationLinkWrap); } }
      else { if(viewLocationLink){ viewLocationLink.href = '#'; hide(viewLocationLinkWrap); } }
      viewModal?.classList.remove('hidden'); viewModal?.classList.add('flex');
    }

    function closeView(){ window.currentRoomId = null; if(viewModal) viewModal.dataset.roomId = ''; viewModal?.classList.add('hidden'); viewModal?.classList.remove('flex'); }
    viewClose?.addEventListener('click', closeView);
    viewCloseBtn?.addEventListener('click', closeView);
    viewModal?.addEventListener('click', (e)=>{ if(e.target===viewModal) closeView(); });

    window.openBooking = function(roomId, defaultStart=null, defaultEnd=null){
      if(!bookingRoomId) return;
      bookingRoomId.value = roomId;
      if(bookingStart) bookingStart.value = defaultStart || '';
      if(bookingEnd) bookingEnd.value = defaultEnd || '';
      if(bookingGuests) bookingGuests.value = 1;
      bookingForm?.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false);
      bookingForm?.querySelector('textarea') && (bookingForm.querySelector('textarea').value = '');
      bookingError && bookingError.classList.add('hidden');
      bookingModal?.classList.remove('hidden'); bookingModal?.classList.add('flex');
      window.currentRoomId = roomId;
    };

    function closeBooking(){ bookingModal?.classList.add('hidden'); bookingModal?.classList.remove('flex'); }
    bookingClose?.addEventListener('click', closeBooking);
    bookingCancel?.addEventListener('click', closeBooking);
    bookingModal?.addEventListener('click', (e)=>{ if(e.target===bookingModal) closeBooking(); });

    function openPaymentModal(id){
      if(!paymentBookingId) return;
      paymentBookingId.value = id;
      paymentForm?.reset();
      paymentError?.classList.add('hidden');
      paymentModal?.classList.remove('hidden'); paymentModal?.classList.add('flex');
    }
    window.openPaymentModal = openPaymentModal;

    function closePayment(){ paymentModal?.classList.add('hidden'); paymentModal?.classList.remove('flex'); }
    paymentClose?.addEventListener('click', closePayment);
    paymentCancel?.addEventListener('click', closePayment);
    paymentModal?.addEventListener('click', e=>{ if(e.target===paymentModal) closePayment(); });

    paymentForm?.addEventListener('submit', async e=>{
      e.preventDefault();
      paymentSubmit.disabled = true;
      paymentError?.classList.add('hidden');
      try{
        const fd = new FormData(paymentForm);
        const res = await fetch('api/booking_payment_upload.php',{method:'POST',body:fd,credentials:'include'});
        const txt = await res.text();
        let data; try{ data = JSON.parse(txt); } catch{ data={success:false,message:txt}; }
        if(!data.success){ throw new Error(data.message || 'Upload failed'); }
        closePayment();
        alert('Payment proof uploaded. Awaiting approval.');
      } catch(err){
        paymentError && (paymentError.textContent = err.message || err, paymentError.classList.remove('hidden'));
      } finally {
        paymentSubmit.disabled = false;
      }
    });

    // Book Now handler robustness
    (function attachViewBookNow(){
      const attempt = () => {
        const b = document.getElementById('viewBookBtn');
        if(!b) return setTimeout(attempt, 150);
        b.addEventListener('click', (e)=>{ 
          e.preventDefault(); 
          const id = window.currentRoomId || (viewModal && viewModal.dataset && viewModal.dataset.roomId) || null; 
          if(!id){ alert('Room not selected.'); return; } 
          closeView(); 
          openBooking(id); 
        });
      };
      attempt();
    })();

    // Filters reload
    [selLoc, minR, maxR].forEach(el => el && el.addEventListener('change', loadRooms));

    /* ===========================
       Approvals (Admin/Manager)
       =========================== */

    openApprovalsBtn?.addEventListener('click', async () => {
      approvalsError?.classList.add('hidden');
      approvalsModal?.classList.remove('hidden');
      approvalsModal?.classList.add('flex');
      await loadPendingApprovals();
    });
    approvalsClose?.addEventListener('click', () => {
      approvalsModal?.classList.add('hidden');
      approvalsModal?.classList.remove('flex');
    });
    approvalsModal?.addEventListener('click', (e) => {
      if (e.target === approvalsModal) {
        approvalsModal?.classList.add('hidden');
        approvalsModal?.classList.remove('flex');
      }
    });

    async function loadPendingApprovals(){
      if (!canManage) return;
      approvalsList.innerHTML = '';
      approvalsEmpty?.classList.add('hidden');

      try {
        const res = await fetch('api/bookings_list_pending.php', { credentials:'include' });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Failed to load approvals');

        const items = data.bookings || [];
        if (!items.length) {
          approvalsEmpty?.classList.remove('hidden');
          approvalsBadge?.classList.add('hidden');
          approvalsBadge && (approvalsBadge.textContent = '0');
          return;
        }

        approvalsBadge?.classList.remove('hidden');
        approvalsBadge && (approvalsBadge.textContent = String(items.length));

        approvalsList.innerHTML = items.map(b => {
          const title = (b.room_title || '(Untitled)').replace(/</g,'&lt;');
          const dates = `${b.start_date} → ${b.end_date}`;
          const pax   = `${b.guests || 1} guest${(b.guests||1)>1?'s':''}`;
          return `
            <div class="rounded-xl border p-3 flex items-center justify-between gap-3">
              <div class="min-w-0">
                <div class="font-medium">${title}</div>
                <div class="text-sm text-slate-600">${dates} • ${pax}</div>
              </div>
              <div class="flex items-center gap-2">
                <button data-approve="${b.id}" class="px-3 py-1.5 rounded-full bg-emerald-600 text-white text-sm hover:bg-emerald-700">Approve</button>
                <button data-reject="${b.id}" class="px-3 py-1.5 rounded-full border text-sm hover:bg-slate-50">Reject</button>
              </div>
            </div>
          `;
        }).join('');

        approvalsList.querySelectorAll('[data-approve]').forEach(btn => {
          btn.addEventListener('click', () => updateBookingStatus(btn.dataset.approve, 'approve'));
        });
        approvalsList.querySelectorAll('[data-reject]').forEach(btn => {
          btn.addEventListener('click', async () => {
            const reason = prompt('Reason for rejection? (optional)');
            await updateBookingStatus(btn.dataset.reject, 'reject', reason || '');
          });
        });
      } catch (err) {
        approvalsError && (approvalsError.textContent = err.message || String(err));
        approvalsError?.classList.remove('hidden');
      }
    }

    async function refreshApprovalsBadge(){
      try {
        const res = await fetch('api/bookings_list_pending.php', { credentials:'include' });
        const data = await res.json();
        if (!data.success) throw new Error();
        const n = (data.bookings || []).length;
        if (n > 0) {
          approvalsBadge?.classList.remove('hidden');
          approvalsBadge && (approvalsBadge.textContent = String(n));
        } else {
          approvalsBadge?.classList.add('hidden');
          approvalsBadge && (approvalsBadge.textContent = '0');
        }
      } catch { /* silent */ }
    }

    async function updateBookingStatus(id, action, reason=''){
      const fd = new FormData();
      fd.append('booking_id', id);
      fd.append('action', action);
      if (action === 'reject') fd.append('reason', reason);

      try {
        const res = await fetch('api/booking_update_status.php', { method:'POST', body: fd, credentials:'include' });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Failed to update');

        await loadPendingApprovals();
        await refreshApprovalsBadge();
      } catch (err) {
        alert(err.message || String(err));
      }
    }
  });
})();
