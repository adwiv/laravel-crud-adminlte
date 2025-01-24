/// crud-edit.js 1.0.0

///
/// Handle slug updation
///
function slugify(text) {
  text = text.toString().toLowerCase().trim();
  return text
    .replace(/&/g, "-and-") // Replace & with 'and'
    .replace(/[\s\W-]+/g, "-") // Replace spaces, non-word characters and dashes with a single dash (-)
    .replace(/-$/, ""); // Remove last floating dash if exists
}

$(document).ready(function () {
  $(".slug-source").on("propertychange keyup input cut paste", function () {
    const sinkId = this.dataset.slugSink;
    const sink = document.getElementById(sinkId);
    if (sink) sink.value = slugify($(this).val());
  });
});

///
/// Handle form validation before submission
///
(function () {
  "use strict";
  window.addEventListener(
    "load",
    function () {
      // Fetch all the forms we want to apply custom Bootstrap validation styles to
      let forms = document.getElementsByClassName("needs-validation");
      // Loop over them and prevent submission
      let validation = Array.prototype.filter.call(forms, function (form) {
        form.addEventListener(
          "submit",
          function (event) {
            if (form.checkValidity() === false) {
              event.preventDefault();
              event.stopPropagation();
            }
            form.classList.add("was-validated");
          },
          false
        );
      });
    },
    false
  );
})();

///
/// Handle navigation with unsaved changes
///

// Initialize variables
let formModified = false;

function trackFormModification(formName) {
  let form = document.getElementById(formName);
  if (!form) console.error(`Form not found: ${formName}`);
  form?.addEventListener("input", (e) => {
    formModified = true;
  });
  form?.addEventListener("reset", (e) => {
    formModified = false;
  });
  form?.addEventListener("submit", (e) => {
    formModified = false;
  });
}

// Function to handle navigation with confirmation
async function handleNavigation(url) {
  if (!formModified) {
    window.location.href = url;
    return;
  }

  const result = await Swal.fire({
    title: "Unsaved Changes",
    text: "You have unsaved changes. Are you sure you want to leave this page?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Yes, leave page",
    cancelButtonText: "Stay on page",
    allowOutsideClick: false, // Prevent clicking outside to dismiss
    allowEscapeKey: false, // Prevent ESC key from dismissing
  });

  if (result.isConfirmed) {
    window.location.href = url;
  }
}

// Handle clicks on links
document.addEventListener("click", (e) => {
  if (formModified) {
    const link = e.target.closest("a");
    if (link && link.href) {
      e.preventDefault();
      handleNavigation(link.href);
    }
  }
});

// Handle browser navigation (back button, closing tab, etc.)
// window.addEventListener("beforeunload", (e) => {
//   if (formModified) {
//     e.preventDefault();
//     e.returnValue = ""; // Required for browser compatibility
//     return e.returnValue;
//   }
// });
