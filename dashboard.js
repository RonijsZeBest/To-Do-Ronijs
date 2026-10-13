const taskForm = document.querySelector("#task-form");
const taskInput = document.querySelector("#task-input");
const taskList = document.querySelector("#task-list");
const taskTemplate = document.querySelector("#task-template");
const emptyState = document.querySelector("#empty-state");
const filterButtons = document.querySelectorAll(".filter");

let tasks = [];
let activeFilter = "all";

function formatDate(value) {
  if (!value) {
    return "—";
  }

  const [year, month, day] = value.slice(0, 10).split("-").map(Number);
  const dateParts = new Intl.DateTimeFormat("lv-LV", {
    year: "numeric",
    month: "long",
    day: "numeric",
  }).formatToParts(new Date(year, month - 1, day));

  const monthName = dateParts.find(part => part.type === "month").value;
  const dayNumber = dateParts.find(part => part.type === "day").value;
  const yearNumber = dateParts.find(part => part.type === "year").value;

  return `${dayNumber}. ${monthName} ${yearNumber}.`;
}

function formatDateTime(value) {
  if (!value) {
    return "—";
  }

  return new Intl.DateTimeFormat("lv-LV", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function getVisibleTasks() {
  if (activeFilter === "active") {
    return tasks.filter(task => !task.completed);
  }

  if (activeFilter === "completed") {
    return tasks.filter(task => task.completed);
  }

  return tasks;
}

function getEmptyStateMessage() {
  if (tasks.length === 0) {
    return "Uzdevumu saraksts ir tukšs. Pievieno uzdevumu, lai sāktu!";
  }

  if (activeFilter === "active") {
    return "Visi uzdevumi pabeigti. Lieliski!";
  }

  return "Vēl nav pabeigtu uzdevumu.";
}

function createTaskRow(task, index) {
  const row = taskTemplate.content.firstElementChild.cloneNode(true);
  const checkButton = row.querySelector(".task-check");
  const deleteButton = row.querySelector(".task-delete");
  const dueDate = row.querySelector(".task-date");
  const priority = row.querySelector(".task-priority");
  const priorityLevel = typeof task.priority === "boolean"
    ? (task.priority ? "high" : "medium")
    : task.priority;
  const priorityLabels = {
    low: "Zema",
    medium: "Vidēja",
    high: "Augsta",
  };

  row.dataset.id = task.id;
  row.classList.toggle("completed", task.completed);
  row.querySelector(".task-number").textContent = String(index + 1);
  row.querySelector(".task-title").textContent = task.title;
  row.querySelector(".task-status").textContent = task.completed ? "Pabeigts" : "Aktīvs";
  row.querySelector(".task-assignee").textContent = task.assignee || "—";

  dueDate.textContent = formatDate(task.due_at);
  dueDate.title = `Pēdējoreiz atjaunināts: ${formatDateTime(task.updated_at)}`;

  priority.textContent = priorityLabels[priorityLevel] || priorityLabels.medium;
  priority.classList.toggle("priority-low", priorityLevel === "low");
  priority.classList.toggle("priority-medium", priorityLevel === "medium");
  priority.classList.toggle("priority-high", priorityLevel === "high");

  checkButton.setAttribute(
    "aria-label",
    task.completed
      ? "Atzīmēt uzdevumu kā nepabeigtu"
      : "Atzīmēt uzdevumu kā pabeigtu"
  );
  checkButton.addEventListener("click", () => toggleTask(task.id));
  deleteButton.addEventListener("click", () => deleteTask(task.id));

  return row;
}

function renderTasks() {
  const visibleTasks = getVisibleTasks();

  taskList.replaceChildren();
  emptyState.hidden = visibleTasks.length > 0;
  emptyState.textContent = getEmptyStateMessage();

  visibleTasks.forEach((task, index) => {
    taskList.append(createTaskRow(task, index));
  });
}

async function loadTasks() {
  const response = await fetch("todos.php");

  if (!response.ok) {
    throw new Error("Nevarēja ielādēt uzdevumus.");
  }

  const data = await response.json();
  tasks = data.tasks;
  renderTasks();
}

async function sendTaskAction(body, errorMessage) {
  const response = await fetch("todos.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body,
  });

  if (!response.ok) {
    throw new Error(errorMessage);
  }

  await loadTasks();
}

async function saveTask(title, assignee, dueAt, priority, id = null) {
  const body = new URLSearchParams({
    action: "add",
    task: title,
    assignee,
    due_at: dueAt,
    priority,
  });

  if (id) {
    body.set("id", id);
  }

  await sendTaskAction(body, "Nevarēja saglabāt uzdevumu.");
}

async function updateTask(action, id) {
  const body = new URLSearchParams({ action, id });
  await sendTaskAction(body, "Nevarēja atjaunot uzdevumu.");
}

async function toggleTask(id) {
  await updateTask("toggle", id);
}

async function deleteTask(id) {
  const task = tasks.find(item => item.id === id);

  if (!task || !window.confirm(`Vai dzēst uzdevumu “${task.title}”?`)) {
    return;
  }

  await updateTask("delete", id);
}

async function handleTaskSubmit(event) {
  event.preventDefault();

  const title = taskInput.value.trim();
  const assignee = document.querySelector("#task-assignee").value.trim();
  const dueDateInput = document.querySelector("#task-due");
  const dueAt = dueDateInput.value;
  const priority = document.querySelector("#task-priority").value;

  if (!title) {
    return;
  }

  try {
    await saveTask(title, assignee, dueAt, priority);
    taskInput.value = "";
    dueDateInput.value = "";
    taskInput.focus();
  } catch (error) {
    window.alert(error.message);
  }
}

function setActiveFilter(button) {
  activeFilter = button.dataset.filter;

  filterButtons.forEach(filter => {
    filter.classList.toggle("active", filter === button);
  });

  renderTasks();
}

taskForm.addEventListener("submit", handleTaskSubmit);

filterButtons.forEach(button => {
  button.addEventListener("click", () => setActiveFilter(button));
});

loadTasks().catch(error => window.alert(error.message));
