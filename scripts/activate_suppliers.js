import axios from 'axios';

const BASE = process.env.BASE || 'http://127.0.0.1:8000/api';
const EMAIL = process.env.EMAIL || 'admin@klinik.com';
const PASSWORD = process.env.PASSWORD || 'password123';

async function login() {
  try {
    const res = await axios.post(`${BASE}/login`, {
      email: EMAIL,
      password: PASSWORD,
    });
    return res.data.data.token;
  } catch (err) {
    console.error('Login failed:', err.response?.data || err.message);
    process.exit(1);
  }
}

async function fetchSuppliers(token) {
  try {
    const res = await axios.get(`${BASE}/suppliers?per_page=1000`, {
      headers: { Authorization: `Bearer ${token}` },
    });

    // Support both paginated and array response
    if (Array.isArray(res.data.data)) return res.data.data;
    if (res.data.data?.data) return res.data.data.data;
    return [];
  } catch (err) {
    console.error('Fetch suppliers failed:', err.response?.data || err.message);
    process.exit(1);
  }
}

async function activateSupplier(token, supplier) {
  try {
    // Only send fields that are safe / likely to exist in older schemas
    const payload = {
      name: supplier.name || 'Unnamed',
      phone: supplier.phone || null,
      address: supplier.address || null,
      is_active: true,
    };

    const res = await axios.put(`${BASE}/suppliers/${supplier.id}`, payload, {
      headers: { Authorization: `Bearer ${token}` },
    });
    return res.data;
  } catch (err) {
    console.error(`Failed to activate supplier ${supplier.id}:`, err.response?.data || err.message);
    return null;
  }
}

async function main() {
  console.log('Logging in as', EMAIL);
  const token = await login();

  console.log('Fetching suppliers...');
  const suppliers = await fetchSuppliers(token);
  console.log(`Found ${suppliers.length} suppliers`);

  const inactive = suppliers.filter((s) => !s.is_active);
  console.log(`Inactive suppliers: ${inactive.length}`);

  let updated = 0;
  for (const sup of inactive) {
    const res = await activateSupplier(token, sup);
    if (res && res.success) {
      console.log(`Activated supplier ${sup.id} - ${sup.name}`);
      updated++;
    }
  }

  console.log(`Activation complete. Updated: ${updated}`);
  console.log('Now refresh frontend or re-open Purchases page.');
}

main();
