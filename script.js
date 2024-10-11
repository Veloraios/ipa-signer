document.getElementById('signerForm').onsubmit = async function(event) {
    event.preventDefault();
    const formData = new FormData(this);
    
    const response = await fetch('sign.php', {
        method: 'POST',
        body: formData,
    });
    
    const result = await response.text();
    document.getElementById('output').innerHTML = result;
};
