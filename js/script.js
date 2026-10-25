const ingredients = [
  { name: "Raw mango", quantity: 350, unit: "g" },
  { name: "Sesame oil", quantity: 100, unit: "ml" },
  { name: "Pickle salt", quantity: 50, unit: "g" },
  { name: "Chilli powder", quantity: 40, unit: "g" },
  { name: "Mustard seeds", quantity: 15, unit: "g" },
  { name: "Fenugreek seeds", quantity: 5, unit: "g" }
];
// Demonstration baseline: ingredients listed above are for 500 ml.
function renderIngredients() {
  const qty = Number(document.getElementById("targetQty").value);
  const unit = document.getElementById("targetUnit").value;
  if (!Number.isFinite(qty) || qty <= 0) {
    alert("Enter a quantity greater than zero.");
    return;
  }
  const requestedMl = unit === "L" ? qty * 1000 : qty;
  const factor = requestedMl / 500;
  document.getElementById("resultTitle").textContent = "Mango Pickle";
  document.getElementById("resultSubtitle").textContent =
    `Ingredient list for ${qty} ${unit}`;
  document.getElementById("ingredientRows").innerHTML = ingredients.map(item => {
    const amount = Math.round(item.quantity * factor * 100) / 100;
    return `<tr><td>${item.name}</td><td>${amount} ${item.unit}</td></tr>`;
  }).join("");
}
document.getElementById("calculateBtn").addEventListener("click", renderIngredients);
document.getElementById("printBtn").addEventListener("click", () => window.print());
document.getElementById("pdfBtn").addEventListener("click", () => {
  // In the print dialog, choose "Save as PDF".
  window.print();
});
renderIngredients();

const loginForm = document.getElementById("loginForm");
if (loginForm) {
  loginForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const response = await fetch("login.php", { method: "POST", body: new FormData(loginForm) });
    const data = await response.json();
    document.getElementById("loginMessage").textContent = data.message;
    if (response.ok && data.ok) window.location.reload();
  });
}
const pinForm = document.getElementById("pinForm");
if (pinForm) {
  pinForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const response = await fetch("secret_recipe.php", { method: "POST", body: new FormData(pinForm) });
    const data = await response.json();
    const message = document.getElementById("recipeMessage");
    message.textContent = data.message;
    const box = document.getElementById("secretRecipe");
    if (response.ok && data.ok) {
      box.hidden = false;
      box.className = "recipe-box";
      box.textContent = data.recipe; // Render as text; never inject secret HTML.
    } else {
      box.hidden = true;
      box.textContent = "";
    }
  });
}
