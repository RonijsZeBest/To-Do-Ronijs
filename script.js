const form = document.querySelector("#login-form, #signup-form");

if (form) {
  const emailInput = document.querySelector("#email");
  const passwordInput = document.querySelector("#password");
  const passwordToggle = document.querySelector(".password-toggle");
  const nameInput = document.querySelector("#name");
  const confirmInput = document.querySelector("#confirm");
  const termsInput = document.querySelector('input[name="terms"]');
  const statusMessage = document.querySelector("#status");
  const submitButton = form.querySelector(".btn");
  const submitLabel = submitButton.querySelector("span");
  const isSignup = form.id === "signup-form";

  function setFieldError(input, message) {
    const errorMessage = document.querySelector(`#${input.id}-error`);

    input.classList.toggle("invalid", Boolean(message));
    input.setAttribute("aria-invalid", String(Boolean(message)));

    if (errorMessage) {
      errorMessage.textContent = message;
    }
  }

  function validateField(input) {
    const value = input.value.trim();
    let message = "";

    if (!value) {
      message = "Šis lauks ir jāaizpilda.";
    } else if (input === emailInput && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
      message = "Ievadi derīgu e-pasta adresi.";
    } else if (input === passwordInput && value.length < 6) {
      message = "Parolei jābūt vismaz 6 rakstzīmes garai.";
    } else if (input === confirmInput && value !== passwordInput.value) {
      message = "Paroles nesakrīt.";
    }

    setFieldError(input, message);
    return message === "";
  }

  function validateForm() {
    const requiredInputs = [nameInput, emailInput, passwordInput].filter(Boolean);
    const requiredFieldsAreValid = requiredInputs.map(validateField).every(Boolean);
    const confirmationIsValid = !confirmInput || validateField(confirmInput);
    const termsAreValid = !termsInput || termsInput.checked;

    if (requiredFieldsAreValid && confirmationIsValid && termsAreValid) {
      return true;
    }

    statusMessage.textContent = "Pārbaudi laukus, kas atzīmēti ar kļūdu.";
    statusMessage.style.color = "var(--danger)";
    (form.querySelector(".invalid") || emailInput || passwordInput).focus();
    return false;
  }

  function handlePasswordToggle() {
    const isVisible = passwordInput.type === "text";

    passwordInput.type = isVisible ? "password" : "text";
    passwordToggle.classList.toggle("visible", !isVisible);
    passwordToggle.setAttribute("aria-pressed", String(!isVisible));
    passwordToggle.setAttribute("aria-label", isVisible ? "Rādīt paroli" : "Slēpt paroli");
    passwordInput.focus();
  }

  async function handleSubmit(event) {
    event.preventDefault();

    if (!validateForm()) {
      return;
    }

    submitButton.disabled = true;
    submitLabel.textContent = isSignup
      ? "Notiek konta izveide…"
      : "Notiek pierakstīšanās…";
    statusMessage.textContent = "";

    const endpoint = isSignup ? "signup.php" : "login.php";

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        body: new FormData(form),
      });
      const data = await response.json();

      statusMessage.textContent = data.message;
      statusMessage.style.color = response.ok ? "var(--green)" : "var(--danger)";

      if (response.ok && isSignup) {
        window.setTimeout(() => window.location.replace("index.html"), 1000);
      } else if (response.ok) {
        window.location.replace("dashboard.php");
      }
    } catch {
      statusMessage.textContent = "Radās servera kļūda. Mēģini vēlreiz vēlāk.";
      statusMessage.style.color = "var(--danger)";
    } finally {
      submitButton.disabled = false;
      submitLabel.textContent = isSignup ? "Izveidot kontu" : "Pierakstīties";
    }
  }

  form.querySelectorAll("input:not([type='checkbox'])").forEach(input => {
    input.addEventListener("blur", () => validateField(input));
    input.addEventListener("input", () => {
      if (input.classList.contains("invalid")) {
        validateField(input);
      }
    });
  });

  if (passwordToggle) {
    passwordToggle.addEventListener("click", handlePasswordToggle);
  }

  form.addEventListener("submit", handleSubmit);
}
