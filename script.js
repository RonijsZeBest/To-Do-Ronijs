const form = document.querySelector("#login-form, #signup-form");
const emailInput = document.querySelector("#email");
const passwordInput = document.querySelector("#password");
const passwordToggle = document.querySelector(".password-toggle");
const status = document.querySelector("#status");
const nameInput = document.querySelector("#name");
const confirmInput = document.querySelector("#confirm");
const termsInput = document.querySelector('input[name="terms"]');

function setFieldError(input, message) {
  const error = document.querySelector(`#${input.id}-error`);
  input.classList.toggle("invalid", Boolean(message));
  input.setAttribute("aria-invalid", String(Boolean(message)));
  if (error) error.textContent = message;
}

function validateField(input) {
  const value = input.value.trim();
  let message = "";

  if (!value) {
    message = "Šis lauks ir obligāts.";
  } else if (input === emailInput && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
    message = "Ievadiet derīgu e-pasta adresi.";
  } else if (input === passwordInput && value.length < 6) {
    message = "Parole jābūt 6 rakstzīmēm.";
  } else if (input === confirmInput && value !== passwordInput.value) {
    message = "Parole neapstiprinās.";
  }

  setFieldError(input, message);
  return !message;
}

[...form.querySelectorAll("input:not([type='checkbox'])")].forEach(input => {
  input.addEventListener("blur", () => validateField(input));
  input.addEventListener("input", () => {
    if (input.classList.contains("invalid")) validateField(input);
  });
});

if (passwordToggle) {
  passwordToggle.addEventListener("click", () => {
    const isVisible = passwordInput.type === "text";
    passwordInput.type = isVisible ? "password" : "text";
    passwordToggle.classList.toggle("visible", !isVisible);
    passwordToggle.setAttribute("aria-pressed", String(!isVisible));
    passwordToggle.setAttribute("aria-label", isVisible ? "Rādīt paroli" : "Paslēpt paroli");
    passwordInput.focus();
  });
}

form.addEventListener("submit", event => {
  event.preventDefault();
  const fields = [nameInput, emailInput, passwordInput].filter(Boolean);
  const validFields = fields.map(validateField).every(Boolean);
  const confirmationIsValid = !confirmInput || validateField(confirmInput);
  const termsAreValid = !termsInput || termsInput.checked;

  if (!validFields || !confirmationIsValid || !termsAreValid) {
    status.textContent = "Pārbaudiet iezīmētās vērtības.";
    status.style.color = "var(--danger)";
    (form.querySelector(".invalid") || emailInput || passwordInput).focus();
    return;
  }

  const submitButton = form.querySelector(".btn");
  const isSignup = form.id === "signup-form";
  submitButton.disabled = true;
  submitButton.querySelector("span").textContent = isSignup ? "Izveidot kontu…" : "Ielogoties…";
  status.textContent = "";

  const endpoint = isSignup ? "signup.php" : "login.php";

  fetch(endpoint, { method: "POST", body: new FormData(form) })
    .then(res => res.json().then(data => ({ ok: res.ok, data })))
    .then(({ ok, data }) => {
      status.textContent = data.message;
      status.style.color = ok ? "var(--green)" : "var(--danger)";
      if (ok && isSignup) {
        window.setTimeout(() => window.location.replace("index.html"), 1000);
        return;
      }
      if (ok && !isSignup) {
        window.location.replace("dashboard.php");
      }
    })
    .catch(() => {
      status.textContent = "Servera kļūda. Mēģini vēlāk.";
      status.style.color = "var(--danger)";
    })
    .finally(() => {
      submitButton.disabled = false;
      submitButton.querySelector("span").textContent = isSignup ? "Izveidot kontu" : "Ielogoties";
    });
});