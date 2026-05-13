document.addEventListener("DOMContentLoaded", function(){

    const form = document.getElementById("sbLoginForm");
    if(!form) return;

    form.addEventListener("submit", function(e){
        e.preventDefault();

        let formData = new FormData(form);
        formData.append("action", "sb_do_login");

        fetch(SB_LOGIN.ajax_url, {
            method: "POST",
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if(res.success){
                window.location.href = res.data.redirect;
            } else {
                document.getElementById("sbLoginMsg").innerText = res.data;
            }
        });

    });

});