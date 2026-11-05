export async function login(a) {
  await a.go('/login');
  const needs = await a.js(`return !!document.querySelector('input[name=email]')`);
  if (!needs) return;
  await a.js(`document.querySelector('input[name=email]').value='demo@avantti.test'; document.querySelector('input[name=password]').value='DemoPass!2026'; document.querySelector('form').submit();`);
  await a.sleep(2500);
}
