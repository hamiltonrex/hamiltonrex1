function openModal(candidato) {
    document.getElementById('editId').value = candidato.id;
    document.getElementById('editNome').value = candidato.nome;
    document.getElementById('editRg').value = candidato.rg;
    document.getElementById('editCpf').value = candidato.cpf;
    document.getElementById('editPix').value = candidato.pix;
    document.getElementById('editTelefone').value = candidato.telefone; // Correção: adicionado
    document.getElementById('editEmail').value = candidato.email;     // Correção: adicionado
    document.getElementById('editModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

document.getElementById("editForm").addEventListener("submit", function(e) {
    e.preventDefault();
    var formData = new FormData(this);

    fetch("atualizar_candidato.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.text())
    .then(result => {
        if (result === "Sucesso") {
            alert("Candidato atualizado com sucesso.");
            closeModal(); // Fecha o modal
            location.reload(); // Recarrega a página para mostrar as alterações
        } else {
            alert("Erro ao atualizar: " + result);
        }
    })
    .catch(error => {
        console.error("Erro ao enviar dados:", error);
    });
});
