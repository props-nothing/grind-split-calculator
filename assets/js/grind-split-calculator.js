(function () {
  "use strict";

  function formatNumber(value, decimals) {
    const number = Number(value || 0);
    return number.toLocaleString("nl-NL", {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    });
  }

  function normalizeNumericString(value) {
    let normalized = String(value || "")
      .trim()
      .replace(/\s+/g, "");
    if (normalized.includes(",") && normalized.includes(".")) {
      normalized = normalized.replace(/\./g, "").replace(",", ".");
    } else if (normalized.includes(",")) {
      normalized = normalized.replace(",", ".");
    } else if (normalized.includes(".")) {
      const parts = normalized.split(".");
      if (parts.length === 2 && parts[1].length === 3) {
        normalized = normalized.replace(".", "");
      }
    }
    return Number(normalized);
  }

  function parseQuantityLabel(rawValue) {
    const pattern = /^([\d\.,]+)\s*kg\s+(\w+)\s+([\d\.,]+)m3$/i;
    const value = String(rawValue || "").trim();
    const match = value.match(pattern);
    if (!match) {
      return null;
    }

    const weight = normalizeNumericString(match[1]);
    const bagType = String(match[2] || "").trim();
    const volume = normalizeNumericString(match[3]);

    if (!weight || !volume) {
      return null;
    }

    return {
      weight_per_bag: weight,
      bag_type: bagType,
      volume_per_bag: volume,
    };
  }

  function findBestQuantity(volumeNeeded, quantities) {
    if (!quantities || !quantities.length) return null;
    if (volumeNeeded <= 0) return quantities[0];

    let bestQuantity = null;
    let minExcess = Infinity;
    let minBags = Infinity;

    for (const q of quantities) {
      const parsed = parseQuantityLabel(q.label) || q;
      const bagVol = Number(parsed.volume_per_bag || 1);
      const bags = Math.ceil(volumeNeeded / bagVol);
      const totalVol = bags * bagVol;
      const excess = totalVol - volumeNeeded;

      if (excess < minExcess) {
        minExcess = excess;
        minBags = bags;
        bestQuantity = q;
      } else if (Math.abs(excess - minExcess) < 0.001) {
        if (bags < minBags) {
          minBags = bags;
          bestQuantity = q;
        }
      }
    }
    return bestQuantity;
  }

  function calculateResult(sqm, thicknessCm, quantity) {
    const area = Number(sqm || 0);
    const thickness = Number(thicknessCm || 0);
    if (!quantity || area <= 0 || thickness <= 0) {
      return {
        volumeNeeded: 0,
        bagsNeeded: 0,
      };
    }

    const volumeNeeded = area * (thickness / 100);
    const parsed = parseQuantityLabel(quantity.label || "") || quantity;
    const bagVol = Number(parsed.volume_per_bag || 1);
    const bagsNeeded = Math.ceil(volumeNeeded / bagVol);

    return {
      volumeNeeded,
      bagsNeeded,
    };
  }

  function updateResultUI(container, quantity, result, thickness) {
    const packageEl = container.querySelector("[data-gsc-result-package]");
    if (packageEl) {
      packageEl.textContent = quantity ? quantity.label : "-";
    }

    container.querySelector("[data-gsc-result-volume]").textContent =
      formatNumber(result.volumeNeeded, 2);
    container.querySelector("[data-gsc-result-bags]").textContent = String(
      result.bagsNeeded,
    );

    const note = container.querySelector("[data-gsc-note]");
    if (note) {
      note.textContent = gscData.i18n.calculationNote.replace(
        "%s",
        formatNumber(thickness, 1),
      );
    }

    const addToCartButton = container.querySelector("[data-gsc-add-to-cart]");
    if (addToCartButton && quantity) {
      const type = quantity.bag_type
        ? quantity.bag_type
        : gscData.i18n.bagFallback;
      addToCartButton.textContent = gscData.i18n.addToCartLabel
        .replace("%1$s", String(result.bagsNeeded))
        .replace("%2$s", String(type));
    }
  }

  async function postAjax(action, payload) {
    const body = new URLSearchParams();
    body.append("action", action);
    Object.keys(payload).forEach((key) => {
      body.append(key, payload[key]);
    });

    const response = await fetch(gscData.ajaxUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: body.toString(),
    });

    return response.json();
  }

  function addMessage(container, message) {
    const el = container.querySelector("[data-gsc-message]");
    if (el) {
      el.textContent = message || "";
    }
  }

  function buildAddToCartUrl(productId, variationId, formatSlug, quantitySlug) {
    const url = new URL(gscData.cartUrl, window.location.origin);
    url.searchParams.set("add-to-cart", String(productId));
    url.searchParams.set("variation_id", String(variationId));
    if (formatSlug) {
      url.searchParams.set(
        `attribute_${gscData.attributeFormaat}`,
        String(formatSlug),
      );
    }
    url.searchParams.set(
      `attribute_${gscData.attributeQty}`,
      String(quantitySlug),
    );
    return url.toString();
  }

  function initCalculator(container) {
    const configRaw = container.getAttribute("data-config");
    if (!configRaw) {
      return;
    }

    const config = JSON.parse(configRaw);
    const formatSelect = container.querySelector("[data-gsc-format]");
    const quantitySelect = container.querySelector("[data-gsc-quantity]");
    const sqmInput = container.querySelector("[data-gsc-sqm]");
    const thicknessInput = container.querySelector("[data-gsc-thickness]");
    const addToCartButton = container.querySelector("[data-gsc-add-to-cart]");

    const quantitiesByFormat = config.quantitiesByFormat || {};

    function renderFormats() {
      formatSelect.innerHTML = "";
      if (!config.hasFormats) {
        const option = document.createElement("option");
        option.value = "";
        option.textContent = "N/A";
        formatSelect.appendChild(option);
        return;
      }
      (config.formats || []).forEach((format) => {
        const option = document.createElement("option");
        option.value = format.slug;
        option.textContent = format.name;
        formatSelect.appendChild(option);
      });
    }

    function renderQuantities() {
      const selectedFormat = formatSelect.value || "";
      const quantities = quantitiesByFormat[selectedFormat] || [];
      quantitySelect.innerHTML = "";

      quantities.forEach((quantity) => {
        const option = document.createElement("option");
        option.value = quantity.slug;
        option.textContent = `${quantity.label} (${quantity.bag_type}, ${formatNumber(quantity.volume_per_bag, 1)} m³)`;
        quantitySelect.appendChild(option);
      });
    }

    function getSelectedQuantity() {
      const selectedFormat = formatSelect.value || "";
      const selectedQuantitySlug = quantitySelect.value;
      const quantities = quantitiesByFormat[selectedFormat] || [];
      const selected =
        quantities.find((item) => item.slug === selectedQuantitySlug) || null;
      if (!selected) {
        return null;
      }

      if (
        selected.volume_per_bag &&
        selected.weight_per_bag &&
        selected.bag_type
      ) {
        return selected;
      }

      const parsed = parseQuantityLabel(selected.label || "");
      return parsed ? { ...selected, ...parsed } : selected;
    }

    function recalculate() {
      const area = Number(sqmInput.value || 0);
      const thickness = Number(thicknessInput.value || 0);
      const volumeNeeded = area * (thickness / 100);

      const selectedFormat = formatSelect.value || "";
      const quantities = quantitiesByFormat[selectedFormat] || [];

      const bestQuantity = findBestQuantity(volumeNeeded, quantities);
      if (bestQuantity) {
        quantitySelect.value = bestQuantity.slug;
      }

      const quantity = getSelectedQuantity();
      const result = calculateResult(
        sqmInput.value,
        thicknessInput.value,
        quantity,
      );
      updateResultUI(
        container,
        quantity,
        result,
        thicknessInput.value || config.layerThickness || 5,
      );
    }

    renderFormats();
    renderQuantities();
    recalculate();

    formatSelect.addEventListener("change", () => {
      renderQuantities();
      recalculate();
    });
    // quantitySelect.addEventListener('change', recalculate); // Removed manual change listener
    sqmInput.addEventListener("input", recalculate);
    thicknessInput.addEventListener("input", recalculate);

    addToCartButton.addEventListener("click", async () => {
      addMessage(container, "");
      const selectedQuantity = getSelectedQuantity();
      if (
        !selectedQuantity ||
        !quantitySelect.value ||
        (config.hasFormats && !formatSelect.value)
      ) {
        addMessage(container, gscData.i18n.needSelections);
        return;
      }

      const result = calculateResult(
        sqmInput.value,
        thicknessInput.value,
        selectedQuantity,
      );
      if (result.bagsNeeded <= 0) {
        addMessage(container, gscData.i18n.invalidInput);
        return;
      }

      const response = await postAjax("grind_get_variation_id", {
        product_id: config.productId,
        format: formatSelect.value || "",
        quantity: quantitySelect.value,
      });

      if (!response || !response.success || !response.data.variation_id) {
        addMessage(container, gscData.i18n.variationMissing);
        return;
      }

      const addUrl = buildAddToCartUrl(
        config.productId,
        response.data.variation_id,
        formatSelect.value,
        quantitySelect.value,
      );
      window.location.href = `${addUrl}&quantity=${result.bagsNeeded}`;
    });
  }

  function initWizard(container) {
    const configRaw = container.getAttribute("data-config");
    const config = configRaw
      ? JSON.parse(configRaw)
      : { categories: [], productsByCategory: {} };
    const stepElements = {
      1: container.querySelector('[data-gsc-step="1"]'),
      2: container.querySelector('[data-gsc-step="2"]'),
      3: container.querySelector('[data-gsc-step="3"]'),
      4: container.querySelector('[data-gsc-step="4"]'),
    };
    const progressSteps = container.querySelectorAll(".gsc-progress-step");
    const categoryCardsContainer = container.querySelector(
      "[data-gsc-category-cards]",
    );
    const productCardsContainer = container.querySelector(
      "[data-gsc-product-cards]",
    );
    const formatCardsContainer = container.querySelector(
      "[data-gsc-format-cards]",
    );
    const quantityOptionsContainer = container.querySelector(
      "[data-gsc-quantity-options]",
    );
    const sqmInput = container.querySelector("[data-gsc-sqm]");
    const thicknessInput = container.querySelector("[data-gsc-thickness]");
    const addToCartButton = container.querySelector("[data-gsc-add-to-cart]");
    const state = {
      categoryId: null,
      productId: null,
      format: null,
      quantity: null,
      quantityData: null,
      availableQuantities: [],
    };

    const savedStateRaw = sessionStorage.getItem("gscWizardState");
    if (savedStateRaw) {
      try {
        Object.assign(state, JSON.parse(savedStateRaw));
      } catch (error) {
        sessionStorage.removeItem("gscWizardState");
      }
    }

    function persistState() {
      sessionStorage.setItem("gscWizardState", JSON.stringify(state));
    }

    function setStep(stepNumber, interact = false) {
      if (interact) {
        // Lock height to prevent scroll jump when switching to a shorter step
        const currentHeight = container.offsetHeight;
        container.style.minHeight = `${currentHeight}px`;
      }

      Object.keys(stepElements).forEach((key) => {
        const element = stepElements[key];
        if (!element) {
          return;
        }
        element.classList.toggle(
          "is-active",
          Number(key) === Number(stepNumber),
        );
      });

      progressSteps.forEach((element) => {
        const index = Number(element.getAttribute("data-step"));
        element.classList.toggle("is-active", index <= stepNumber);

        // Hide step 3 if there are no formats
        if (index === 3) {
          element.style.display = state.format === "" ? "none" : "";
        }
      });

      if (interact) {
        const rect = container.getBoundingClientRect();
        if (window.innerWidth <= 640 || rect.top < 0) {
          const offset = 80; // Account for potential sticky headers
          const topPos = rect.top + window.scrollY - offset;
          window.scrollTo({ top: topPos, behavior: "smooth" });
        }

        // Remove min-height lock after transition
        setTimeout(() => {
          container.style.minHeight = "";
        }, 400);
      }
    }

    progressSteps.forEach((element) => {
      element.addEventListener("click", () => {
        const targetStep = Number(element.getAttribute("data-step"));

        // Only allow navigating to previous steps or the immediate next step if current is valid
        if (targetStep === 1) {
          setStep(1, true);
        } else if (targetStep === 2 && state.categoryId) {
          setStep(2, true);
        } else if (targetStep === 3 && state.productId) {
          if (state.format === "") {
            // Skip step 3 if no formats
            setStep(4, true);
          } else {
            setStep(3, true);
          }
        } else if (targetStep === 4 && state.format !== null) {
          setStep(4, true);
        }
      });
    });

    function getResult() {
      const area = Number(sqmInput.value || 0);
      const thickness = Number(thicknessInput.value || 0);
      const volumeNeeded = area * (thickness / 100);

      const bestQuantity = findBestQuantity(
        volumeNeeded,
        state.availableQuantities || [],
      );
      if (bestQuantity) {
        const parsed =
          parseQuantityLabel(bestQuantity.label || "") || bestQuantity;
        state.quantity = bestQuantity.slug;
        state.quantityData = {
          ...bestQuantity,
          ...parsed,
        };
        persistState();
      } else {
        state.quantity = null;
        state.quantityData = null;
      }

      return calculateResult(
        sqmInput.value,
        thicknessInput.value,
        state.quantityData,
      );
    }

    function recalculate() {
      const result = getResult();
      updateResultUI(
        container,
        state.quantityData,
        result,
        thicknessInput.value || 5,
      );
    }

    function renderFormats(formats) {
      formatCardsContainer.innerHTML = "";
      if (!formats.length) {
        formatCardsContainer.innerHTML = `<p class="gsc-empty">${gscData.i18n.noFormats}</p>`;
        return;
      }

      formats.forEach((format) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "gsc-card";
        button.dataset.gscFormat = format.slug;
        button.innerHTML = `<strong>${format.name}</strong><span>${format.name}</span>`;
        button.addEventListener("click", async () => {
          formatCardsContainer
            .querySelectorAll(".gsc-card")
            .forEach((card) => card.classList.remove("is-selected"));
          button.classList.add("is-selected");
          state.format = format.slug;
          state.quantity = null;
          state.quantityData = null;
          state.availableQuantities = [];
          persistState();
          await loadQuantities(true);
        });
        formatCardsContainer.appendChild(button);
      });
    }

    function renderQuantities(quantities) {
      // We no longer render quantity pills, we just store them for auto-calculation
      state.availableQuantities = quantities;
      quantityOptionsContainer.innerHTML = "";
      if (!quantities.length) {
        quantityOptionsContainer.innerHTML = `<p class="gsc-empty">${gscData.i18n.noQuantities}</p>`;
      }
    }

    async function loadFormats(interact = false) {
      if (!state.productId) {
        return;
      }
      const response = await postAjax("grind_get_formats", {
        product_id: state.productId,
      });

      if (!response || !response.success) {
        return;
      }

      if (response.data.layer_thickness) {
        thicknessInput.value = response.data.layer_thickness;
      }

      const formats = response.data.formats || [];
      if (formats.length === 0) {
        // Product has no formats, skip to step 4
        state.format = "";
        persistState();
        await loadQuantities(interact);
        return;
      }

      renderFormats(formats);
      setStep(3, interact);
    }

    async function loadQuantities(interact = false) {
      if (!state.productId || state.format === null) {
        return;
      }
      const response = await postAjax("grind_get_quantities", {
        product_id: state.productId,
        format: state.format || "",
      });
      if (!response || !response.success) {
        return;
      }

      if (response.data.layer_thickness) {
        thicknessInput.value = response.data.layer_thickness;
      }

      renderQuantities(response.data.quantities || []);
      setStep(4, interact);
      recalculate();
    }

    function renderCategories() {
      if (!categoryCardsContainer) {
        return;
      }

      categoryCardsContainer.innerHTML = "";
      const categories = Array.isArray(config.categories)
        ? config.categories
        : [];
      if (!categories.length) {
        categoryCardsContainer.innerHTML = `<p class="gsc-empty">${gscData.i18n.noCategories}</p>`;
        return;
      }

      categories.forEach((category) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "gsc-card";
        button.dataset.gscCategory = String(category.id);
        button.innerHTML = `${category.image ? `<img src="${category.image}" alt="${category.name}" />` : ""}<strong>${category.name}</strong><span>${category.description || ""}</span>`;
        button.addEventListener("click", () => {
          categoryCardsContainer
            .querySelectorAll(".gsc-card")
            .forEach((card) => card.classList.remove("is-selected"));
          button.classList.add("is-selected");
          state.categoryId = Number(category.id);
          state.productId = null;
          state.format = null;
          state.quantity = null;
          state.quantityData = null;
          state.availableQuantities = [];
          persistState();
          renderProductsForCategory();
          setStep(2, true);
        });
        categoryCardsContainer.appendChild(button);
      });
    }

    function renderProductsForCategory() {
      if (!productCardsContainer) {
        return;
      }

      productCardsContainer.innerHTML = "";
      if (!state.categoryId) {
        productCardsContainer.innerHTML = `<p class="gsc-empty">${gscData.i18n.selectCategory}</p>`;
        return;
      }

      const products =
        (config.productsByCategory &&
          config.productsByCategory[String(state.categoryId)]) ||
        [];
      if (!products.length) {
        productCardsContainer.innerHTML = `<p class="gsc-empty">${gscData.i18n.noProducts}</p>`;
        return;
      }

      products.forEach((product) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "gsc-card";
        button.dataset.gscProduct = String(product.id);
        button.innerHTML = `${product.image ? `<img src="${product.image}" alt="${product.name}" />` : ""}<strong>${product.name}</strong>`;
        button.addEventListener("click", async () => {
          productCardsContainer
            .querySelectorAll(".gsc-card")
            .forEach((card) => card.classList.remove("is-selected"));
          button.classList.add("is-selected");
          state.productId = Number(product.id);
          state.format = null;
          state.quantity = null;
          state.quantityData = null;
          state.availableQuantities = [];
          persistState();
          await loadFormats(true);
        });
        productCardsContainer.appendChild(button);
      });
    }

    container.querySelectorAll("[data-gsc-back-step]").forEach((button) => {
      button.addEventListener("click", () => {
        let target = Number(button.getAttribute("data-gsc-back-step"));

        // If going back to step 3, but we have no formats, skip to step 2
        if (target === 3 && state.format === "") {
          target = 2;
        }

        setStep(target, true);
      });
    });

    sqmInput.addEventListener("input", recalculate);
    thicknessInput.addEventListener("input", recalculate);

    addToCartButton.addEventListener("click", async () => {
      addMessage(container, "");
      if (!state.productId || state.format === null || !state.quantity) {
        addMessage(container, gscData.i18n.needSelections);
        return;
      }

      const result = getResult();
      if (result.bagsNeeded <= 0) {
        addMessage(container, gscData.i18n.invalidInput);
        return;
      }

      const response = await postAjax("grind_get_variation_id", {
        product_id: state.productId,
        format: state.format || "",
        quantity: state.quantity,
      });
      if (!response || !response.success || !response.data.variation_id) {
        addMessage(container, gscData.i18n.variationMissing);
        return;
      }

      const addUrl = buildAddToCartUrl(
        state.productId,
        response.data.variation_id,
        state.format || "",
        state.quantity,
      );
      window.location.href = `${addUrl}&quantity=${result.bagsNeeded}`;
    });

    renderCategories();
    renderProductsForCategory();

    if (state.productId && state.format !== null) {
      if (state.format === "") {
        setStep(4);
      } else {
        setStep(3);
      }
      loadQuantities();
    } else if (state.productId) {
      setStep(2);
      loadFormats();
    } else if (state.categoryId) {
      setStep(2);
    } else {
      setStep(1);
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-gsc-calculator]").forEach((element) => {
      initCalculator(element);
    });

    document.querySelectorAll("[data-gsc-wizard]").forEach((element) => {
      initWizard(element);
    });
  });
})();
