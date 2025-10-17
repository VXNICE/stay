
async function api(path, options = {}) {
  const opts = {
    credentials: 'include',
    ...options,
  };


  if (!(opts.body instanceof FormData)) {
    opts.headers = {
      'Content-Type': 'application/json',
      ...(opts.headers || {}),
    };
  }

  const res = await fetch(`/api/${path}`, opts);

  
  let data;
  try {
    data = await res.json();
  } catch {
    const text = await res.text();
    throw new Error(res.ok ? text : `HTTP ${res.status} ${text}`);
  }

  if (!res.ok || (data && data.success === false)) {
    throw new Error(data.error || data.message || 'Request failed');
  }

  return data;
}
