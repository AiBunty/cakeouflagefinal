window.CakeouflageModal = {
  open(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.hidden = false;
  },
  close(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.hidden = true;
  }
};
