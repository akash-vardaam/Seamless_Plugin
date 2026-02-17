jQuery(document).ready(function ($) {
  // --- Events Controller ---
  const eventsTableBody = $("#seamless-events-table-body");
  const eventsPagination = $("#seamless-events-pagination");

  // Check if on events tab on load
  const isEventsTab =
    $('.nav-tab-active[data-tab="events"]').length > 0 ||
    new URLSearchParams(window.location.search).get("tab") === "events";
  const isMembershipTab =
    $('.nav-tab-active[data-tab="membership"]').length > 0 ||
    new URLSearchParams(window.location.search).get("tab") === "membership";

  let allEvents = [];
  let filteredEvents = [];
  let eventsPage = 1;
  const itemsPerPage = 15;

  // Helper to get tab from link
  function getTabFromLink(link) {
    if (!link) return null;
    const $link = $(link);
    if ($link.data("tab")) return $link.data("tab");

    const href = $link.attr("href");
    if (!href) return null;

    try {
      // Check for simple query
      const match = href.match(/[?&]tab=([^&]+)/);
      if (match) return match[1];

      // Full URL check
      const urlObj = new URL(href, window.location.origin);
      return urlObj.searchParams.get("tab");
    } catch (e) {
      return null;
    }
  }

  function renderEventsTable() {
    if (!eventsTableBody.length) return;

    eventsTableBody.empty();
    const start = (eventsPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const pageItems = filteredEvents.slice(start, end);

    if (pageItems.length === 0) {
      eventsTableBody.append(
        '<tr><td colspan="6" style="text-align:center;">No events found.</td></tr>'
      );
      renderEventsPagination();
      return;
    }

    pageItems.forEach((event, index) => {
      let startDate = "-";
      let endDate = "-";

      // Handle date variations
      if (event.event_date_range) {
        if (event.event_date_range.start)
          startDate = new Date(event.event_date_range.start).toLocaleString();
        if (event.event_date_range.end)
          endDate = new Date(event.event_date_range.end).toLocaleString();
      } else if (event.start_date || event.end_date) {
        if (event.start_date)
          startDate = new Date(event.start_date).toLocaleString();
        if (event.end_date) endDate = new Date(event.end_date).toLocaleString();
      }

      // formatted_* override
      if (event.formatted_start_date) startDate = event.formatted_start_date;
      if (event.formatted_end_date) endDate = event.formatted_end_date;

      // Type handling
      let typeLabel = "Event";
      let badgeClass = "seamless-status-badge rfc-compliant";

      if (event.event_type === "group_event") {
        typeLabel = "Group Event";
        badgeClass = "seamless-status-badge disabled";
      }

      const slug = event.slug || "";
      const shortcode = `[seamless_single_event slug="${slug}"]`;

      const row = `
                <tr>
                    <td>${start + index + 1}</td>
                    <td><strong>${event.title || "Untitled"}</strong></td>
                    <td>${startDate}</td>
                    <td>${endDate}</td>
                    <td><span class="${badgeClass}">${typeLabel}</span></td>
                    <td>
                        <div class="shortcode-container">
                            <code class="seamless-code-block">${shortcode}</code>
                            <button type="button" class="copy-shortcode-btn" data-shortcode='${shortcode}' title="Copy">
                                <span class="dashicons dashicons-admin-page"></span>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
      eventsTableBody.append(row);
    });

    renderEventsPagination();
  }

  function renderEventsPagination() {
    if (!eventsPagination.length) return;

    eventsPagination.empty();
    const totalPages = Math.ceil(filteredEvents.length / itemsPerPage);
    if (totalPages <= 1) return;

    let html = "";
    if (eventsPage > 1) {
      html += `<a href="#" class="page-numbers prev" data-page="${
        eventsPage - 1
      }">« Prev</a>`;
    }
    for (let i = 1; i <= totalPages; i++) {
      if (
        i === 1 ||
        i === totalPages ||
        (i >= eventsPage - 2 && i <= eventsPage + 2)
      ) {
        const current = i === eventsPage ? "current" : "";
        html += `<a href="#" class="page-numbers ${current}" data-page="${i}">${i}</a>`;
      } else if (i === eventsPage - 3 || i === eventsPage + 3) {
        html += `<span class="page-numbers dots">...</span>`;
      }
    }
    if (eventsPage < totalPages) {
      html += `<a href="#" class="page-numbers next" data-page="${
        eventsPage + 1
      }">Next »</a>`;
    }
    eventsPagination.html(html);
  }

  async function loadEvents(force = false) {
    if (!eventsTableBody.length) {
      console.log("Seamless Admin: Events table not found.");
      return;
    }

    // Skip if already loaded unless forcing
    if (!force && eventsTableBody.data("loaded") === "true") {
      console.log("Seamless Admin: Events already loaded.");
      return;
    }

    console.log("Seamless Admin: Loading events...");

    // Show loader scoped to table
    const loader = $(
      '<div class="seamless-admin-loader"><div class="seamless-admin-spinner"></div><div class="seamless-admin-loading-text">Loading Events...</div></div>'
    );
    $(".seamless-table-area").has(eventsTableBody).append(loader); // Append to wrapper

    // Also simple fallback
    eventsTableBody.html(
      '<tr><td colspan="6" style="text-align:center; padding: 20px;">Loading...</td></tr>'
    );

    try {
      if (!window.SeamlessAPI) {
        console.error("Seamless Admin: API Client not found.");
        eventsTableBody.html(
          '<tr><td colspan="6" style="text-align:center; color:red;">API Client not loaded. Refresh page.</td></tr>'
        );
        loader.remove();
        return;
      }

      const results = await window.SeamlessAPI.fetchAllEvents();
      console.log(
        "Seamless Admin: Fetched events:",
        results ? results.length : 0
      );

      // Sort by start date desc
      if (results && results.length > 0) {
        results.sort((a, b) => {
          const dateA = new Date(
            a.start_date || a.event_date_range?.start || 0
          );
          const dateB = new Date(
            b.start_date || b.event_date_range?.start || 0
          );
          return dateB - dateA;
        });
      }

      allEvents = results || [];
      filteredEvents = allEvents;
      eventsPage = 1;
      eventsTableBody.data("loaded", "true");
      renderEventsTable();
    } catch (error) {
      console.error("Seamless Admin: Error loading events", error);
      eventsTableBody.html(
        `<tr><td colspan="6" style="text-align:center; color:red;">Error loading events: ${
          error.message || "Unknown error"
        }</td></tr>`
      );
    } finally {
      loader.remove();
    }
  }

  // Events Search & Controls
  $("#seamless-events-search").on("input", function () {
    const term = $(this).val().toLowerCase();

    if (term.length > 0) {
      $("#seamless-events-reset").show();
    } else {
      $("#seamless-events-reset").hide();
    }

    filteredEvents = allEvents.filter((e) =>
      (e.title || "").toLowerCase().includes(term)
    );
    eventsPage = 1;
    renderEventsTable();
  });

  $("#seamless-events-reset").on("click", function (event) {
    event.preventDefault();
    $("#seamless-events-search").val("").trigger("input");
  });

  // Events Pagination Click
  eventsPagination.on("click", "a.page-numbers", function (e) {
    e.preventDefault();
    const page = $(this).data("page");
    if (page) {
      eventsPage = parseInt(page);
      renderEventsTable();
    }
  });

  // --- Membership Controller ---
  const memTableBody = $("#seamless-membership-table-body");
  const memPagination = $("#seamless-membership-pagination");
  let allPlans = [];
  let filteredPlans = [];
  let memPage = 1;

  function renderMemTable() {
    if (!memTableBody.length) return;

    memTableBody.empty();
    const start = (memPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const pageItems = filteredPlans.slice(start, end);

    if (pageItems.length === 0) {
      memTableBody.append(
        '<tr><td colspan="8" style="text-align:center;">No membership plans found.</td></tr>'
      );
      renderMemPagination();
      return;
    }

    pageItems.forEach((plan, index) => {
      const price = parseFloat(plan.price || 0).toFixed(2);
      const status = plan.is_active
        ? '<span style="color: #16a34a; background-color: #f0fdf4; padding: 5px; border-radius: 5px; font-weight: bold; font-size: 12px;">Active</span>'
        : '<span style="color: #d63638; background-color: #fbeaea; padding: 5px; border-radius: 5px; font-weight: bold; font-size: 12px;">Inactive</span>';
      const shortcode = `[seamless_single_membership id="${plan.id}"]`;

      const row = `
                <tr>
                    <td>${start + index + 1}</td>
                    <td><strong>${plan.label || "Untitled"}</strong></td>
                    <td>${plan.sku || "-"}</td>
                    <td>$${price}</td>
                    <td>${plan.billing_cycle_display || "-"}</td>
                    <td>${plan.trial_days || "0"}</td>
                    <td>${status}</td>
                    <td>
                        <div class="shortcode-container">
                            <code class="seamless-code-block">${shortcode}</code>
                            <button type="button" class="copy-shortcode-btn" data-shortcode='${shortcode}' title="Copy">
                                <span class="dashicons dashicons-admin-page"></span>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
      memTableBody.append(row);
    });

    renderMemPagination();
  }

  function renderMemPagination() {
    if (!memPagination.length) return;

    memPagination.empty();
    const totalPages = Math.ceil(filteredPlans.length / itemsPerPage);
    if (totalPages <= 1) return;

    let html = "";
    if (memPage > 1) {
      html += `<a href="#" class="page-numbers prev" data-page="${
        memPage - 1
      }">« Prev</a>`;
    }
    for (let i = 1; i <= totalPages; i++) {
      if (
        i === 1 ||
        i === totalPages ||
        (i >= memPage - 2 && i <= memPage + 2)
      ) {
        const current = i === memPage ? "current" : "";
        html += `<a href="#" class="page-numbers ${current}" data-page="${i}">${i}</a>`;
      } else if (i === memPage - 3 || i === memPage + 3) {
        html += `<span class="page-numbers dots">...</span>`;
      }
    }
    if (memPage < totalPages) {
      html += `<a href="#" class="page-numbers next" data-page="${
        memPage + 1
      }">Next »</a>`;
    }
    memPagination.html(html);
  }

  async function loadMemberships(force = false) {
    if (!memTableBody.length) return;

    if (!force && memTableBody.data("loaded") === "true") {
      console.log("Seamless Admin: Memberships already loaded.");
      return;
    }

    console.log("Seamless Admin: Loading memberships...");

    // Loader
    const loader = $(
      '<div class="seamless-admin-loader"><div class="seamless-admin-spinner"></div><div class="seamless-admin-loading-text">Loading Memberships...</div></div>'
    );
    $(".seamless-table-area").has(memTableBody).append(loader);

    memTableBody.html(
      '<tr><td colspan="8" style="text-align:center; padding: 20px;">Loading...</td></tr>'
    );

    try {
      if (!window.SeamlessAPI) {
        console.error("Seamless Admin: API Client not found.");
        memTableBody.html(
          '<tr><td colspan="8" style="text-align:center; color:red;">API Client not loaded. Refresh page.</td></tr>'
        );
        loader.remove();
        return;
      }
      const response = await window.SeamlessAPI.getAllMembershipPlans();
      allPlans = response || [];
      filteredPlans = allPlans;
      memPage = 1;
      memTableBody.data("loaded", "true");
      renderMemTable();
    } catch (error) {
      console.error(error);
      memTableBody.html(
        `<tr><td colspan="8" style="text-align:center; color:red;">Error loading memberships: ${error.message}</td></tr>`
      );
    } finally {
      loader.remove();
    }
  }

  // Mem Search & Controls
  $("#seamless-membership-search").on("input", function () {
    const term = $(this).val().toLowerCase();

    if (term.length > 0) {
      $("#seamless-membership-reset").show();
    } else {
      $("#seamless-membership-reset").hide();
    }

    filteredPlans = allPlans.filter((p) =>
      (p.label || "").toLowerCase().includes(term)
    );
    memPage = 1;
    renderMemTable();
  });

  $("#seamless-membership-reset").on("click", function () {
    $("#seamless-membership-search").val("").trigger("input");
  });

  // Mem Pagination Click
  memPagination.on("click", "a.page-numbers", function (e) {
    e.preventDefault();
    const page = $(this).data("page");
    if (page) {
      memPage = parseInt(page);
      renderMemTable();
    }
  });

  // --- Tab Handling ---

  // Function to handle switching tabs from click event or initial load
  function handleTabSwitch(tabName) {
    console.log("Seamless Admin: Switching to tab:", tabName);

    // Manual tab switching logic for single-page app feel
    // Hide all panels
    $(".seamless-tab-panel").removeClass("is-active").hide();

    // Show target panel
    const $targetPanel = $(`.seamless-tab-panel[data-tab="${tabName}"]`);
    $targetPanel.addClass("is-active").show();

    // Update nav-tab-active classes
    $(".nav-tab").removeClass("nav-tab-active");
    $(`.nav-tab[data-tab="${tabName}"]`).addClass("nav-tab-active");

    // Trigger data load
    if (tabName === "events") {
      loadEvents();
    } else if (tabName === "membership") {
      loadMemberships();
    }
  }

  // Initial Load
  if (isEventsTab) {
    // Ensure UI matches if URL param differs (robustness)
    handleTabSwitch("events");
  } else if (isMembershipTab) {
    handleTabSwitch("membership");
  }

  // Listen for tab clicks
  $(document).on("click", ".nav-tab", function (e) {
    e.preventDefault(); // Prevent page reload
    const tab = getTabFromLink(this);
    if (tab) {
      // Update URL without reload
      const newUrl = new URL(window.location);
      newUrl.searchParams.set("tab", tab);
      window.history.pushState({}, "", newUrl);

      handleTabSwitch(tab);
    }
  });

  // Copy shortcode handler
  $(document).on("click", ".copy-shortcode-btn", function () {
    const shortcode = $(this).data("shortcode");
    navigator.clipboard.writeText(shortcode).then(() => {
      const originalIcon = $(this).html();
      $(this).html('<span class="dashicons dashicons-yes"></span>');
      setTimeout(() => {
        $(this).html(originalIcon);
      }, 1500);
    });
  });
});
