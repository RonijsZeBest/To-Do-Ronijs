const taskForm = document.querySelector("#task-form");
const taskInput = document.querySelector("#task-input");
const taskList = document.querySelector("#task-list");
const taskTemplate = document.querySelector("#task-template");
const emptyState = document.querySelector("#empty-state");
const filterButtons = document.querySelectorAll(".filter");
const taskListSelect = document.querySelector("#task-list-select");
const createListButton = document.querySelector("#create-list-button");
const createListDialog = document.querySelector("#create-list-dialog");
const createListForm = document.querySelector("#create-list-form");
const newListName = document.querySelector("#new-list-name");
const cancelCreateListButton = document.querySelector("#cancel-create-list");
const profileButton = document.querySelector("#profile-button");
const profileOptions = document.querySelector("#profile-options");
const deleteAccountButton = document.querySelector("#delete-account-button");

let tasks = [];
let activeFilter = "all";
let activeListId = taskListSelect.value;

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
  const editButton = row.querySelector(".task-edit");
  const saveButton = row.querySelector(".task-save");
  const cancelButton = row.querySelector(".task-cancel");
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
  editButton.addEventListener("click", () => editTask(row, task));
  saveButton.addEventListener("click", () => saveTaskChanges(row, task));
  cancelButton.addEventListener("click", renderTasks);
  deleteButton.addEventListener("click", () => deleteTask(task.id));

  return row;
}

function createEditInput(className, value, type = "text") {
  const input = document.createElement("input");
  input.className = className;
  input.type = type;
  input.value = value;
  input.setAttribute(
    "aria-label",
    className === "task-edit-title"
      ? "Uzdevuma nosaukums"
      : className === "task-edit-assignee"
        ? "Atbildīgais"
        : "Termiņš"
  );
  return input;
}

function editTask(row, task) {
  row.querySelector(".task-title").replaceWith(
    createEditInput("task-edit-title", task.title)
  );
  row.querySelector(".task-assignee").replaceWith(
    createEditInput("task-edit-assignee", task.assignee || "")
  );
  row.querySelector(".task-date").replaceWith(
    createEditInput("task-edit-date", task.due_at ? task.due_at.slice(0, 10) : "", "date")
  );

  const priorityLevel = typeof task.priority === "boolean"
    ? (task.priority ? "high" : "medium")
    : task.priority;
  const prioritySelect = document.createElement("select");
  prioritySelect.className = "task-edit-priority";
  prioritySelect.setAttribute("aria-label", "Prioritāte");
  [
    ["low", "Zema"],
    ["medium", "Vidēja"],
    ["high", "Augsta"],
  ].forEach(([value, label]) => {
    prioritySelect.add(new Option(label, value));
  });
  prioritySelect.value = priorityLevel || "medium";
  row.querySelector(".task-priority").replaceWith(prioritySelect);

  row.querySelector(".task-check").hidden = true;
  row.querySelector(".task-edit").hidden = true;
  row.querySelector(".task-save").hidden = false;
  row.querySelector(".task-cancel").hidden = false;
  row.querySelector(".task-edit-title").focus();
}

async function saveTaskChanges(row, task) {
  const title = row.querySelector(".task-edit-title").value.trim();

  if (!title) {
    window.alert("Uzdevuma nosaukums nevar būt tukšs.");
    row.querySelector(".task-edit-title").focus();
    return;
  }

  const changes = {
    task: title,
    assignee: row.querySelector(".task-edit-assignee").value.trim(),
    due_at: row.querySelector(".task-edit-date").value,
    priority: row.querySelector(".task-edit-priority").value,
  };

  row.querySelector(".task-save").disabled = true;

  try {
    await updateTask("update", task.id, changes);
  } catch (error) {
    window.alert(error.message);
    row.querySelector(".task-save").disabled = false;
  }
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

async function loadTasks(listId = activeListId) {
  const query = new URLSearchParams({ list_id: listId });
  const response = await fetch(`todos.php?${query}`);

  if (!response.ok) {
    throw new Error("Nevarēja ielādēt uzdevumus.");
  }

  const data = await response.json();
  tasks = data.tasks;
  renderTasks();
}

async function loadAvailableLists(selectedListId = activeListId) {
  const response = await fetch("todos.php?action=lists");

  if (!response.ok) {
    throw new Error("Nevarēja ielādēt uzdevumu sarakstus.");
  }

  const data = await response.json();
  taskListSelect.replaceChildren();

  data.lists.forEach(list => {
    const option = new Option(
      list.is_owner
        ? (list.is_default ? "Mans saraksts" : list.name)
        : `${list.name} — koplietots`,
      list.id
    );
    taskListSelect.add(option);
  });

  if ([...taskListSelect.options].some(option => option.value === selectedListId)) {
    taskListSelect.value = selectedListId;
  }

  activeListId = taskListSelect.value;
}

async function sendTaskAction(body, errorMessage, listId = activeListId) {
  body.set("list_id", listId);
  const response = await fetch("todos.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body,
  });

  if (!response.ok) {
    throw new Error(errorMessage);
  }

  await loadTasks(listId);
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

async function updateTask(action, id, changes = {}) {
  const body = new URLSearchParams({ action, id });
  Object.entries(changes).forEach(([key, value]) => body.set(key, value));
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

function toggleProfileMenu() {
  const isExpanded = profileButton.getAttribute("aria-expanded") === "true";
  profileButton.setAttribute("aria-expanded", String(!isExpanded));
  profileOptions.hidden = isExpanded;
}

async function deleteAccount() {
  if (!window.confirm("Vai tiešām vēlies dzēst savu kontu un visus tā uzdevumus? Šo darbību nevar atsaukt.")) {
    return;
  }

  deleteAccountButton.disabled = true;

  try {
    const response = await fetch("delete_account.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        csrf_token: deleteAccountButton.dataset.csrfToken,
      }),
    });
    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.message || "Neizdevās dzēst kontu.");
    }

    window.location.replace("index.html");
  } catch (error) {
    window.alert(error.message);
    deleteAccountButton.disabled = false;
  }
}

async function createTaskList(event) {
  event.preventDefault();
  const name = newListName.value.trim();

  if (!name) {
    newListName.focus();
    return;
  }

  const submitButton = createListForm.querySelector('[type="submit"]');
  submitButton.disabled = true;

  try {
    const response = await fetch("todos.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        action: "create_list",
        name,
        csrf_token: deleteAccountButton.dataset.csrfToken,
      }),
    });
    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.message || "Neizdevās izveidot sarakstu.");
    }

    createListForm.reset();
    createListDialog.close();
    await loadAvailableLists(result.list.id);
    await loadTasks();
  } catch (error) {
    window.alert(error.message);
  } finally {
    submitButton.disabled = false;
  }
}

taskForm.addEventListener("submit", handleTaskSubmit);

filterButtons.forEach(button => {
  button.addEventListener("click", () => setActiveFilter(button));
});

taskListSelect.addEventListener("change", async () => {
  activeListId = taskListSelect.value;

  try {
    await loadTasks();
  } catch (error) {
    window.alert(error.message);
  }
});

createListButton.addEventListener("click", () => {
  createListDialog.showModal();
  newListName.focus();
});
cancelCreateListButton.addEventListener("click", () => createListDialog.close());
createListForm.addEventListener("submit", createTaskList);

profileButton.addEventListener("click", toggleProfileMenu);
deleteAccountButton.addEventListener("click", deleteAccount);

document.addEventListener("click", event => {
  if (!event.target.closest(".account-menu")) {
    profileButton.setAttribute("aria-expanded", "false");
    profileOptions.hidden = true;
  }
});

document.addEventListener("keydown", event => {
  if (event.key === "Escape") {
    profileButton.setAttribute("aria-expanded", "false");
    profileOptions.hidden = true;
    profileButton.focus();
  }
});

async function initializeDashboard() {
  await loadAvailableLists();
  await loadTasks();
}

initializeDashboard().catch(error => window.alert(error.message));
