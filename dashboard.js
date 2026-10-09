const taskForm = document.querySelector("#task-form");
const taskInput = document.querySelector("#task-input");
const taskList = document.querySelector("#task-list");
const taskTemplate = document.querySelector("#task-template");
const emptyState = document.querySelector("#empty-state");
const filters = document.querySelectorAll(".filter");

let tasks = [];
let activeFilter = "all";

function formatDate(value) {
  if (!value) return "—";
  return new Intl.DateTimeFormat(undefined, {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
  }).format(new Date(value));
}

function visibleTasks() {
  if (activeFilter === "active") return tasks.filter(task => !task.completed);
  if (activeFilter === "completed") return tasks.filter(task => task.completed);
  return tasks;
}

function renderTasks() {
  taskList.replaceChildren();
  const visible = visibleTasks();
  const total = tasks.length;

  emptyState.hidden = visible.length > 0;
  emptyState.textContent = total === 0
    ? "Nav nekā darāma. Ievadatat kaut ko, lai sāktu uzdevumu."
    : activeFilter === "active"
      ? "Visi uzdevumi pabeigti."
      : "Vēl nav pabeigtu uzdevumu.";

  visible.forEach((task, index) => {
    const item = taskTemplate.content.firstElementChild.cloneNode(true);
    const checkButton = item.querySelector(".task-check");
    const deleteButton = item.querySelector(".task-delete");
    const number = item.querySelector(".task-number");
    const title = item.querySelector(".task-title");
    const status = item.querySelector(".task-status");
    const assignee = item.querySelector(".task-assignee");
    const priority = item.querySelector(".task-priority");

    item.dataset.id = task.id;
    item.classList.toggle("completed", task.completed);
    number.textContent = String(index + 1);
    title.textContent = task.title;
    status.textContent = task.completed ? "Pabeigts" : "Aktīvs";
    assignee.textContent = task.assignee || "—";
    date.textContent = formatDate(task.due_at);
    date.title = `Atjaunots ${formatDate(task.updated_at)}`;
    priority.textContent = task.priority ? "Jā" : "Nē";
    priority.classList.toggle("priority-high", task.priority);
    checkButton.setAttribute("aria-label", task.completed ? "Vējirā uzdevumu" : "Pabeidziet uzdevumu");
    checkButton.addEventListener("click", () => toggleTask(task.id));
    deleteButton.addEventListener("click", () => deleteTask(task.id));
    taskList.append(item);
  });
}

async function loadTasks() {
  const response = await fetch("todos.php");
  if (!response.ok) throw new Error("Nevarēja ielādēt uzdevumus.");
  const data = await response.json();
  tasks = data.tasks;
  renderTasks();
}

async function saveTask(title, assignee, dueAt, priority, id = null) {
  const body = new URLSearchParams({ action: "add", task: title, assignee, due_at: dueAt });
  if (priority) body.set("priority", "1");
  if (id) body.set("id", id);

  const response = await fetch("todos.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body,
  });
  if (!response.ok) throw new Error("Nevarēja saglabāt uzdevumu.");
  await loadTasks();
}

async function taskAction(action, id) {
  const body = new URLSearchParams({ action, id });
  const response = await fetch("todos.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body,
  });
  if (!response.ok) throw new Error("Nevarēja atjaunot uzdevumu.");
  await loadTasks();
}

async function toggleTask(id) {
  await taskAction("toggle", id);
}

async function deleteTask(id) {
  const task = tasks.find(item => item.id === id);
  if (!task || !window.confirm(`Dzēš “${task.title}”?`)) return;
  await taskAction("delete", id);
}

taskForm.addEventListener("submit", async event => {
  event.preventDefault();
  const title = taskInput.value.trim();
  const assignee = document.querySelector("#task-assignee").value.trim();
  const dueAt = document.querySelector("#task-due").value;
  const priority = document.querySelector('input[name="priority"]').checked;
  if (!title) return;

  try {
    await saveTask(title, assignee, dueAt, priority);
    taskInput.value = "";
    document.querySelector("#task-due").value = "";
    taskInput.focus();
  } catch (error) {
    window.alert(error.message);
  }
});

filters.forEach(button => {
  button.addEventListener("click", () => {
    activeFilter = button.dataset.filter;
    filters.forEach(filter => filter.classList.toggle("active", filter === button));
    renderTasks();
  });
});

loadTasks().catch(error => window.alert(error.message));
