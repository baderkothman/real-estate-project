document.addEventListener("DOMContentLoaded", function () {
  // --------------------------------------
  // NAV TOGGLE (hamburger)
  // --------------------------------------
  var toggle = document.getElementById("navToggle");
  var nav = document.getElementById("mainNav");

  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var isOpen = nav.classList.toggle("app-bar__nav--open");
      toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
      toggle.classList.toggle("app-bar__menu-toggle--open", isOpen);
    });
  }

  // --------------------------------------
  // SAVE / UNSAVE VIA AJAX
  // --------------------------------------
  var saveForms = document.querySelectorAll(".js-save-form");

  saveForms.forEach(function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();

      var button = form.querySelector("[data-save-button]");
      if (!button) return;

      var formData = new FormData(form);

      fetch(form.action, {
        method: "POST",
        body: formData,
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (!data || !data.success) {
            console.error("Save toggle failed", data);
            return;
          }

          var savedNow = !!data.saved;
          button.dataset.saved = savedNow ? "1" : "0";
          button.textContent = savedNow ? "★ Saved" : "☆ Save";

          // Remove card on unsave (for Saved tab)
          if (!savedNow && button.dataset.removeOnUnsave === "1") {
            var card = button.closest(".property-card");
            if (card) card.remove();
          }
        })
        .catch(function (err) {
          console.error("Error toggling save:", err);
        });
    });
  });

  // --------------------------------------
  // SOLD / AVAILABLE VIA AJAX
  // --------------------------------------
  var soldForms = document.querySelectorAll(".js-sold-form");

  soldForms.forEach(function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();

      var button = form.querySelector("[data-sold-button]");
      if (!button) return;

      var formData = new FormData(form);

      fetch(form.action, {
        method: "POST",
        body: formData,
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (!data || !data.success) {
            console.error("Sold toggle failed", data);
            return;
          }

          var isSoldNow = !!data.is_sold;

          // Update button text
          button.textContent = isSoldNow ? "Mark as available" : "Mark as sold";

          // Update availability label
          var card = button.closest(".property-card");
          if (card) {
            var label = card.querySelector("[data-availability-label]");
            if (label) {
              label.textContent =
                "Availability: " + (isSoldNow ? "Sold" : "Available");
            }

            // Remove card if requested
            if (isSoldNow && form.dataset.removeOnSold === "1") {
              card.remove();
            }
            if (!isSoldNow && form.dataset.removeOnAvailable === "1") {
              card.remove();
            }
          }

          // Update header counters if provided
          if (data.counts) {
            var props = data.counts.properties;
            var sold = data.counts.sold;

            var propChip = document.querySelector(
              ".chip[data-counter='properties'] [data-counter-value]"
            );
            var soldChip = document.querySelector(
              ".chip[data-counter='sold'] [data-counter-value]"
            );

            if (propChip != null && typeof props !== "undefined") {
              propChip.textContent = props;
            }
            if (soldChip != null && typeof sold !== "undefined") {
              soldChip.textContent = sold;
            }
          }
        })
        .catch(function (err) {
          console.error("Error toggling sold:", err);
        });
    });
  });

  // --------------------------------------
  // ADMIN: PLAN CHANGE VIA AJAX
  // --------------------------------------
  var planForms = document.querySelectorAll(".js-plan-form");

  planForms.forEach(function (form) {
    var buttons = form.querySelectorAll("[data-plan-button]");

    buttons.forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.preventDefault();

        var selectedPlan = btn.getAttribute("data-plan-value");
        if (!selectedPlan) return;

        var formData = new FormData(form);
        formData.set("plan", selectedPlan); // override

        fetch(form.action, {
          method: "POST",
          body: formData,
        })
          .then(function (response) {
            return response.json();
          })
          .then(function (data) {
            if (!data || !data.success) {
              console.error("Plan update failed", data);
              return;
            }

            // Visually mark the selected plan
            buttons.forEach(function (b) {
              b.classList.remove("btn-primary");
              b.style.background = "transparent";
              b.style.opacity = "0.8";
            });

            btn.classList.add("btn-primary");
            btn.style.background = "";
            btn.style.opacity = "";
          })
          .catch(function (err) {
            console.error("Error updating plan:", err);
          });
      });
    });
  });

  // --------------------------------------
  // PASSWORD SHOW / HIDE TOGGLE (EYE)
  // --------------------------------------
  var passwordWrappers = document.querySelectorAll(
    ".js-password-field, .password-field"
  );

  passwordWrappers.forEach(function (wrapper) {
    var input =
      wrapper.querySelector(".js-password-input") ||
      wrapper.querySelector("input[type='password'], input[type='text']");
    var button =
      wrapper.querySelector(".js-password-toggle") ||
      wrapper.querySelector("[data-password-toggle]");

    if (!input || !button) return;

    var iconShow = button.querySelector(".password-toggle__icon--show");
    var iconHide = button.querySelector(".password-toggle__icon--hide");

    button.addEventListener("click", function () {
      var isHidden = input.type === "password";
      input.type = isHidden ? "text" : "password";

      button.dataset.visible = isHidden ? "1" : "0";
      button.setAttribute(
        "aria-label",
        isHidden ? "Hide password" : "Show password"
      );

      if (iconShow && iconHide) {
        iconShow.style.display = isHidden ? "none" : "";
        iconHide.style.display = isHidden ? "" : "none";
      }
    });
  });
});
