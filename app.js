const modal = document.querySelector("#novo");
document.querySelector("#abrir-modal").addEventListener("click", () => modal.showModal());
document.querySelector("#fechar-modal").addEventListener("click", () => modal.close());

modal.addEventListener("click", (evento) => {
  if (evento.target === modal) modal.close();
});

const alerta = document.querySelector(".alerta");
if (alerta) {
  setTimeout(() => alerta.remove(), 4500);
}
