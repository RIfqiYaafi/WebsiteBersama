// script.js — handler untuk form #kml-form (Convert KML -> CSV/Excel)
document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("kml-form");
  if (!form) return;
  setupFileUpload("kmlFile5", "kml-upload-box", "kml-file-info");

  const btn = document.getElementById("kml-submit");
  const loading = document.getElementById("kml-loading");
  const boxResult = document.getElementById("kml-result");
  const aDownload = document.getElementById("kml-download");
  const spanName = document.getElementById("kml-filename");

  // tampilkan nama file terpilih (opsional)
  const fileInput = document.getElementById("kmlFile");
  const fileInfo = document.getElementById("kml-file-info");
  if (fileInput && fileInfo) {
    fileInput.addEventListener("change", () => {
      fileInfo.textContent = fileInput.files?.[0]?.name || "";
    });
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    boxResult.style.display = "none";
    btn.disabled = true;
    loading.style.display = "inline-block";

    try {
      const fd = new FormData(form);
      // default ke CSV bila select outputFormat tidak ada di form
      if (!fd.get("outputFormat")) fd.set("outputFormat", "csv");

      const res = await fetch("kml_to_csv.php", { method: "POST", body: fd });
      const ctype = res.headers.get("content-type") || "";

      let data;
      if (ctype.includes("application/json")) {
        data = await res.json();
      } else {
        const text = await res.text();
        throw new Error(
          "Server tidak mengirim JSON. Cek path/permission backend.php.\n\nCuplikan balasan:\n" +
            text.slice(0, 400)
        );
      }

      if (!data.success) {
        alert(
          (data.message || "Gagal memproses.") +
            (data.error ? `\nDetail: ${data.error}` : "")
        );
        return;
      }

      // sukses
      spanName.textContent = data.filename || "output";
      aDownload.href = data.download;
      aDownload.setAttribute("download", data.filename || "");
      boxResult.style.display = "block";

      if (data.warning) {
        console.warn("Peringatan backend:\n", data.warning);
      }
    } catch (err) {
      alert(
        "Terjadi kesalahan:\n" +
          (err && err.message ? err.message : String(err))
      );
    } finally {
      btn.disabled = false;
      loading.style.display = "none";
    }
  });
});
