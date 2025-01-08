// Função para alternar o modo escuro
function toggleDarkMode() {
  document.body.classList.toggle("dark");
  
  // Salva a preferência do usuário no localStorage
  const isDarkMode = document.body.classList.contains("dark");
  localStorage.setItem("darkMode", isDarkMode);
  
  // Atualiza o estado do checkbox
  const checkbox = document.getElementById("chk");
  checkbox.checked = isDarkMode;

  // Recarrega a página após a mudança de tema
  setTimeout(() => {
    window.location.reload();
  }, 200); // Pequeno delay para garantir que o localStorage seja atualizado
}

// Função para aplicar o tema baseado na preferência salva
function applyTheme() {
  const isDarkMode = localStorage.getItem("darkMode") === "true";
  const checkbox = document.getElementById("chk");
  
  if (isDarkMode) {
      document.body.classList.add("dark");
      checkbox.checked = true;
  } else {
      document.body.classList.remove("dark");
      checkbox.checked = false;
  }
}

// Aplica o tema quando a página carrega
document.addEventListener("DOMContentLoaded", applyTheme);

// Adiciona evento de mudança ao checkbox
document.getElementById("chk").addEventListener("change", toggleDarkMode);
// Função para mostrar notificações
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.classList.add('notification', type);
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Exemplo de uso após editar o chamado
// showNotification('Chamado editado com sucesso!', 'success');
