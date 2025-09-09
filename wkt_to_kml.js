document.addEventListener("DOMContentLoaded", function () {
  setupFileUpload("excelFile1", "convert-upload-box", "convert-file-info");
});
document
  .getElementById("convert-form")
  .addEventListener("submit", function (e) {
    e.preventDefault();

    let formData = new FormData(this);
    let submitBtn = document.getElementById("convert-submit");
    let loading = document.getElementById("convert-loading");
    let resultDiv = document.getElementById("convert-result");
    let downloadLink = document.getElementById("convert-download");
    let fileNameSpan = document.getElementById("convert-filename");

    // reset tampilan
    resultDiv.style.display = "none";
    submitBtn.disabled = true;
    loading.style.display = "inline-block";

    fetch("wkt_to_kml.php", {
      method: "POST",
      body: formData,
    })
      .then((res) => res.json()) // <- JSON, bukan file
      .then((data) => {
        submitBtn.disabled = false;
        loading.style.display = "none";

        if (data.success) {
          // tampilkan tombol download
          resultDiv.style.display = "block";
          downloadLink.href = data.url;
          fileNameSpan.textContent = data.filename;
        } else {
          alert("Gagal memproses file!");
        }
      })
      .catch((err) => {
        submitBtn.disabled = false;
        loading.style.display = "none";
        alert("Terjadi kesalahan: " + err);
      });
  });
