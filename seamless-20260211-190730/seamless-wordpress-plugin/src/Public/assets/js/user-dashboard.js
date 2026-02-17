/**
 * User Dashboard Widget JavaScript
 * Handles sidebar navigation, membership tabs, and order pagination
 */

(function ($) {
  "use strict";

  class UserDashboardWidget {
    constructor($widget) {
      if ($widget.data("seamless-dashboard-initialized")) {
        return;
      }
      $widget.data("seamless-dashboard-initialized", true);

      this.$widget = $widget;
      this.widgetId = $widget.data("widget-id");
      this.$modal = $("#seamless-user-dashboard-modal-" + this.widgetId);
      this.$form = $("#seamless-user-dashboard-form-" + this.widgetId);
      this.init();
    }

    init() {
      this.bindNavigation();
      this.bindMembershipTabs();
      this.bindEditProfile();
      // bindModalClose is deprecated/empty
      this.bindFormSubmit();
      this.bindPagination();

      // Initialize modals with delegation
      this.initUpgradeModal();
      this.initCancelModal();
      this.initCancelScheduledModal();
      this.initRenewModal();

      this.loadActiveView();
      this.loadDashboardData();
    }

    /**
     * Load all dashboard data in parallel
     */
    loadDashboardData() {
      // Check which tabs/sections are requested via the DOM presence of containers
      if (this.$widget.find("#seamless-dashboard-profile-container").length) {
        this.fetchSection("profile", "seamless-dashboard-profile-container");
      }
      if (
        this.$widget.find("#seamless-dashboard-memberships-container").length
      ) {
        this.fetchSection(
          "memberships",
          "seamless-dashboard-memberships-container",
        );
      }
      if (this.$widget.find("#seamless-dashboard-courses-container").length) {
        this.fetchSection("courses", "seamless-dashboard-courses-container");
      }
      if (this.$widget.find("#seamless-dashboard-orders-container").length) {
        this.fetchSection("orders", "seamless-dashboard-orders-container");
      }
    }

    /**
     * Fetch a specific section via AJAX
     */
    fetchSection(section, containerId) {
      const self = this;
      const $container = this.$widget.find("#" + containerId);

      // Prioritize Core nonce (seamless_ajax) because these actions are handled by SeamlessRender (Core)
      // Fallback to Addon data if Core is missing (rare case)
      let ajaxUrl, nonce;

      if (typeof seamless_ajax !== "undefined") {
        ajaxUrl = seamless_ajax.ajax_url;
        nonce = seamless_ajax.nonce;
      } else if (typeof seamlessAddonData !== "undefined") {
        ajaxUrl = seamlessAddonData.ajaxUrl;
        nonce = seamlessAddonData.nonce;
      } else {
        console.error("Seamless dashboard: No AJAX configuration found.");
        $container.html(
          '<div class="seamless-user-dashboard-error"><p>Configuration error.</p></div>',
        );
        return;
      }

      const requestData = {
        action: "seamless_get_dashboard_" + section,
        nonce: nonce,
        widget_id: this.widgetId,
      };

      // Pass orders_per_page for orders section
      if (
        section === "orders" &&
        typeof seamlessUserDashboard !== "undefined" &&
        seamlessUserDashboard.ordersPerPage
      ) {
        requestData.orders_per_page = seamlessUserDashboard.ordersPerPage;
      }

      $.ajax({
        url: ajaxUrl,
        type: "POST",
        data: requestData,
        success: function (response) {
          if (response.success && response.data.html) {
            $container.html(response.data.html);
            // Reset pagination for the loaded section and update all
            if (section === "orders") {
              self.orderPage = 1;
            } else if (section === "memberships") {
              self.activeMembershipPage = 1;
              self.expiredMembershipPage = 1;
            } else if (section === "courses") {
              self.enrolledCoursePage = 1;
              self.includedCoursePage = 1;
            }

            // Update pagination after a short delay to ensure DOM is ready
            setTimeout(function () {
              self.updateAllPagination();
            }, 100);
          } else {
            $container.html(
              '<div class="seamless-user-dashboard-error"><p>' +
                (response.data.message || "Failed to load content.") +
                "</p></div>",
            );
          }
        },
        error: function () {
          $container.html(
            '<div class="seamless-user-dashboard-error"><p>Network error. Please try again.</p></div>',
          );
        },
      });
    }

    /**
     * Load active view from session storage
     */
    loadActiveView() {
      const savedView = sessionStorage.getItem(
        "seamless-user-dashboard-active-view-" + this.widgetId,
      );
      if (savedView) {
        this.switchView(savedView);
      }
    }

    /**
     * Show toast notification
     * @param {string} message - Message to display
     * @param {string} type - Type of toast: 'success', 'error', 'info'
     * @param {number} duration - Duration in milliseconds (default: 10000)
     */
    showToast(message, type = "success", duration = 6000) {
      // Remove any existing toasts
      $(".seamless-toast").remove();

      // Create toast element
      const $toast = $(`
        <div class="seamless-toast seamless-toast-${type}">
          <div class="seamless-toast-icon">
            ${
              type === "success"
                ? '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>'
                : type === "error"
                  ? '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>'
                  : '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'
            }
          </div>
          <div class="seamless-toast-message">${message}</div>
          <button class="seamless-toast-close" aria-label="Close">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </button>
        </div>
      `);

      // Append to body
      $("body").append($toast);

      // Trigger animation
      setTimeout(() => $toast.addClass("seamless-toast-show"), 10);

      // Close button handler
      $toast.find(".seamless-toast-close").on("click", function () {
        $toast.removeClass("seamless-toast-show");
        setTimeout(() => $toast.remove(), 6000);
      });

      // Auto dismiss
      setTimeout(() => {
        $toast.removeClass("seamless-toast-show");
        setTimeout(() => $toast.remove(), 6000);
      }, duration);
    }

    /**
     * Handle sidebar navigation
     */
    bindNavigation() {
      const self = this;
      this.$widget.on(
        "click",
        ".seamless-user-dashboard-nav-item",
        function (e) {
          const $navItem = $(this);

          // Skip if it's the logout link
          if ($navItem.hasClass("seamless-user-dashboard-nav-logout")) {
            return;
          }

          e.preventDefault();
          const view = $navItem.data("view");
          self.switchView(view);
        },
      );
    }

    /**
     * Switch to a different view
     */
    switchView(view) {
      // Update navigation active state
      this.$widget
        .find(".seamless-user-dashboard-nav-item")
        .removeClass("active");
      this.$widget
        .find('.seamless-user-dashboard-nav-item[data-view="' + view + '"]')
        .addClass("active");

      // Update content view
      this.$widget.find(".seamless-user-dashboard-view").removeClass("active");
      this.$widget
        .find('.seamless-user-dashboard-view[data-view="' + view + '"]')
        .addClass("active");

      // Save to session storage
      sessionStorage.setItem(
        "seamless-user-dashboard-active-view-" + this.widgetId,
        view,
      );

      // Reset pagination when switching views
      const self = this;
      setTimeout(function () {
        self.updateAllPagination();
      }, 100);
    }

    /**
     * Handle tabs (Membership & Courses)
     */
    bindMembershipTabs() {
      const self = this;

      // Event Delegation for Tabs
      this.$widget.on("click", ".seamless-user-dashboard-tab", function () {
        const $tab = $(this);
        const $wrapper = $tab.closest(".seamless-user-dashboard-tabs-wrapper");
        const tabName = $tab.data("tab");

        // Update tab active state within this wrapper only
        $wrapper.find(".seamless-user-dashboard-tab").removeClass("active");
        $tab.addClass("active");

        // Update content within this wrapper only
        $wrapper
          .find(".seamless-user-dashboard-tab-content")
          .removeClass("active");
        $wrapper
          .find(
            '.seamless-user-dashboard-tab-content[data-tab-content="' +
              tabName +
              '"]',
          )
          .addClass("active");

        // Update pagination for the newly shown tab
        setTimeout(function () {
          self.updateAllPagination();
        }, 50);
      });
    }

    /**
     * Handle inline edit profile button click
     */
    bindEditProfile() {
      const self = this;

      // Edit button click
      this.$widget.on(
        "click",
        ".seamless-user-dashboard-btn-edit",
        function () {
          self.enterEditMode();
        },
      );

      // Cancel button click
      this.$widget.on(
        "click",
        ".seamless-user-dashboard-btn-cancel",
        function () {
          self.exitEditMode();
        },
      );

      // Cancel scheduled downgrade button click
      $(document).on(
        "click",
        ".seamless-user-dashboard-cancel-scheduled-btn",
        function () {
          self.handleCancelScheduledDowngrade($(this));
        },
      );

      // Three-dot menu toggle
      $(document).on(
        "click",
        ".seamless-user-dashboard-menu-button",
        function (e) {
          e.preventDefault();
          e.stopPropagation();

          const $button = $(this);
          const $container = $button.closest(
            ".seamless-user-dashboard-menu-container",
          );
          const wasActive = $container.hasClass("active");

          // Close all menus first
          $(".seamless-user-dashboard-menu-container").removeClass("active");

          if (!wasActive) {
            setTimeout(function () {
              $container.addClass("active");
            }, 10);
          }
        },
      );

      // Click outside to close menu
      $(document).on("click", function (e) {
        const $target = $(e.target);

        // Don't close if clicking the button or inside the menu
        if (
          $target.closest(".seamless-user-dashboard-menu-button").length ||
          $target.closest(".seamless-user-dashboard-menu-dropdown").length ||
          $target.closest("#seamless-upgrade-modal").length ||
          $target.closest("#seamless-cancel-modal").length
        ) {
          return;
        }

        // Close all menus
        $(".seamless-user-dashboard-menu-container").removeClass("active");
      });

      // Menu item click - don't auto-close, let modal handle it
      // The dropdown will close when clicking outside or when modal overlay is clicked
    }

    /**
     * Enter edit mode (show form, hide view)
     */
    enterEditMode() {
      // Hide view mode
      this.$widget.find(".seamless-user-dashboard-profile-view-mode").hide();

      // Show edit mode
      this.$widget.find(".seamless-user-dashboard-profile-edit-mode").show();

      // Hide edit button
      this.$widget.find(".seamless-user-dashboard-btn-edit").hide();
    }

    /**
     * Exit edit mode (show view, hide form)
     */
    exitEditMode() {
      // Show view mode
      this.$widget.find(".seamless-user-dashboard-profile-view-mode").show();

      // Hide edit mode
      this.$widget.find(".seamless-user-dashboard-profile-edit-mode").hide();

      // Show edit button
      this.$widget.find(".seamless-user-dashboard-btn-edit").show();

      // Clear any messages
      this.$widget
        .find(".seamless-user-dashboard-form-message")
        .removeClass("success error")
        .hide()
        .text("");
    }

    /**
     * Update profile view with new data (without page reload)
     */
    updateProfileView(data) {
      const fullName = (data.first_name || "") + " " + (data.last_name || "");
      this.$widget
        .find(".seamless-user-dashboard-profile-name")
        .text(fullName.trim());

      const emailValue = data.email || "—";
      this.$widget
        .find(".seamless-user-dashboard-profile-email")
        .text("Email: " + emailValue);

      const fieldMap = {
        first_name: "First Name",
        last_name: "Last Name",
        email: "Email Address",
        phone: "Phone Number",
        address_line_1: "Address Line 1",
        address_line_2: "Address Line 2",
        city: "City",
        state: "State",
        zip_code: "Zip Code",
        country: "Country",
      };

      this.$widget
        .find(".seamless-user-dashboard-profile-field")
        .each(function () {
          const $field = $(this);
          const label = $field.find("label").text().trim();

          // Find matching data field
          for (const [key, fieldLabel] of Object.entries(fieldMap)) {
            if (label === fieldLabel) {
              let value = data[key] || "—";

              // Special handling for phone with type
              if (key === "phone" && data.phone && data.phone_type) {
                value = data.phone + " (" + data.phone_type + ")";
              }

              $field.find(".seamless-user-dashboard-profile-value").text(value);
              break;
            }
          }
        });
    }

    /**
     * Handle modal close (kept for backward compatibility, but not used)
     */
    bindModalClose() {
      // Modal functionality removed - using inline editing now
    }

    /**
     * Handle form submission
     */
    bindFormSubmit() {
      const self = this;
      this.$widget.on(
        "submit",
        ".seamless-user-dashboard-edit-profile-form",
        function (e) {
          e.preventDefault();
          self.$form = $(this); // Update reference to current form
          self.submitForm();
        },
      );
    }

    /**
     * Handle pagination for orders
     */
    bindPagination() {
      const self = this;

      // Track separate page states for different sections
      this.orderPage = 1;
      this.activeMembershipPage = 1;
      this.expiredMembershipPage = 1;
      this.enrolledCoursePage = 1;
      this.includedCoursePage = 1;

      // Previous button
      this.$widget.on(
        "click",
        ".seamless-user-dashboard-pagination-prev",
        function () {
          const $pagination = $(this).closest(
            ".seamless-user-dashboard-pagination",
          );
          const $container = $pagination.closest(
            ".seamless-user-dashboard-orders-container, .seamless-user-dashboard-memberships-container, .seamless-user-dashboard-courses-container",
          );

          if ($container.hasClass("seamless-user-dashboard-orders-container")) {
            if (self.orderPage > 1) {
              self.orderPage--;
              self.updatePagination(
                $container,
                self.orderPage,
                ".seamless-user-dashboard-order-row",
              );
            }
          } else if (
            $container.hasClass("seamless-user-dashboard-memberships-container")
          ) {
            const $tabContent = $container.closest(
              ".seamless-user-dashboard-tab-content",
            );
            const isActive = $tabContent.data("tab-content") === "active";
            if (isActive && self.activeMembershipPage > 1) {
              self.activeMembershipPage--;
              self.updatePagination(
                $container,
                self.activeMembershipPage,
                ".seamless-user-dashboard-membership-card",
              );
            } else if (!isActive && self.expiredMembershipPage > 1) {
              self.expiredMembershipPage--;
              self.updatePagination(
                $container,
                self.expiredMembershipPage,
                ".seamless-user-dashboard-membership-card",
              );
            }
          } else if (
            $container.hasClass("seamless-user-dashboard-courses-container")
          ) {
            const $tabContent = $container.closest(
              ".seamless-user-dashboard-tab-content",
            );
            const isEnrolled = $tabContent.data("tab-content") === "enrolled";
            if (isEnrolled && self.enrolledCoursePage > 1) {
              self.enrolledCoursePage--;
              self.updatePagination(
                $container,
                self.enrolledCoursePage,
                ".seamless-user-dashboard-course-card",
              );
            } else if (!isEnrolled && self.includedCoursePage > 1) {
              self.includedCoursePage--;
              self.updatePagination(
                $container,
                self.includedCoursePage,
                ".seamless-user-dashboard-course-card",
              );
            }
          }
        },
      );

      // Next button
      this.$widget.on(
        "click",
        ".seamless-user-dashboard-pagination-next",
        function () {
          const $pagination = $(this).closest(
            ".seamless-user-dashboard-pagination",
          );
          const $container = $pagination.closest(
            ".seamless-user-dashboard-orders-container, .seamless-user-dashboard-memberships-container, .seamless-user-dashboard-courses-container",
          );
          const totalPages = parseInt($container.data("total-pages")) || 1;

          if ($container.hasClass("seamless-user-dashboard-orders-container")) {
            if (self.orderPage < totalPages) {
              self.orderPage++;
              self.updatePagination(
                $container,
                self.orderPage,
                ".seamless-user-dashboard-order-row",
              );
            }
          } else if (
            $container.hasClass("seamless-user-dashboard-memberships-container")
          ) {
            const $tabContent = $container.closest(
              ".seamless-user-dashboard-tab-content",
            );
            const isActive = $tabContent.data("tab-content") === "active";
            if (isActive && self.activeMembershipPage < totalPages) {
              self.activeMembershipPage++;
              self.updatePagination(
                $container,
                self.activeMembershipPage,
                ".seamless-user-dashboard-membership-card",
              );
            } else if (!isActive && self.expiredMembershipPage < totalPages) {
              self.expiredMembershipPage++;
              self.updatePagination(
                $container,
                self.expiredMembershipPage,
                ".seamless-user-dashboard-membership-card",
              );
            }
          } else if (
            $container.hasClass("seamless-user-dashboard-courses-container")
          ) {
            const $tabContent = $container.closest(
              ".seamless-user-dashboard-tab-content",
            );
            const isEnrolled = $tabContent.data("tab-content") === "enrolled";
            if (isEnrolled && self.enrolledCoursePage < totalPages) {
              self.enrolledCoursePage++;
              self.updatePagination(
                $container,
                self.enrolledCoursePage,
                ".seamless-user-dashboard-course-card",
              );
            } else if (!isEnrolled && self.includedCoursePage < totalPages) {
              self.includedCoursePage++;
              self.updatePagination(
                $container,
                self.includedCoursePage,
                ".seamless-user-dashboard-course-card",
              );
            }
          }
        },
      );

      // Initial pagination attempt (will just return if no content yet)
      this.updateAllPagination();
    }

    /**
     * Update pagination display for a specific container
     */
    updatePagination($container, currentPage, itemSelector) {
      // Defensive checks
      if (!$container || $container.length === 0) {
        return;
      }

      const perPage = parseInt($container.data("per-page")) || 6;
      const totalPages = parseInt($container.data("total-pages")) || 1;
      const $items = $container.find(itemSelector);

      // If no items found, nothing to paginate
      if (!$items || $items.length === 0) {
        return;
      }

      // Calculate range
      const start = (currentPage - 1) * perPage;
      const end = start + perPage;

      // Show/hide items
      $items.each(function (index) {
        if (index >= start && index < end) {
          $(this).show();
        } else {
          $(this).hide();
        }
      });

      // Update pagination controls within this container
      const $pagination = $container.find(
        ".seamless-user-dashboard-pagination",
      );
      $pagination
        .find(".seamless-user-dashboard-current-page")
        .text(currentPage);
      $pagination.find(".seamless-user-dashboard-total-pages").text(totalPages);

      // Update button states
      $pagination
        .find(".seamless-user-dashboard-pagination-prev")
        .prop("disabled", currentPage === 1);
      $pagination
        .find(".seamless-user-dashboard-pagination-next")
        .prop("disabled", currentPage === totalPages);
    }

    /**
     * Update all pagination instances
     */
    updateAllPagination() {
      // Orders
      const $ordersContainer = this.$widget.find(
        ".seamless-user-dashboard-orders-container",
      );
      if ($ordersContainer.length) {
        this.updatePagination(
          $ordersContainer,
          this.orderPage,
          ".seamless-user-dashboard-order-row",
        );
      }

      // Active memberships
      const $activeMemberships = this.$widget.find(
        ".seamless-user-dashboard-tab-content[data-tab-content='active'] .seamless-user-dashboard-memberships-container",
      );
      if ($activeMemberships.length) {
        this.updatePagination(
          $activeMemberships,
          this.activeMembershipPage,
          ".seamless-user-dashboard-membership-card",
        );
      }

      // Expired memberships
      const $expiredMemberships = this.$widget.find(
        ".seamless-user-dashboard-tab-content[data-tab-content='expired'] .seamless-user-dashboard-memberships-container",
      );
      if ($expiredMemberships.length) {
        this.updatePagination(
          $expiredMemberships,
          this.expiredMembershipPage,
          ".seamless-user-dashboard-membership-card",
        );
      }

      // Enrolled courses
      const $enrolledCourses = this.$widget.find(
        ".seamless-user-dashboard-tab-content[data-tab-content='enrolled'] .seamless-user-dashboard-courses-container",
      );
      if ($enrolledCourses.length) {
        this.updatePagination(
          $enrolledCourses,
          this.enrolledCoursePage,
          ".seamless-user-dashboard-course-card",
        );
      }

      // Included courses
      const $includedCourses = this.$widget.find(
        ".seamless-user-dashboard-tab-content[data-tab-content='included'] .seamless-user-dashboard-courses-container",
      );
      if ($includedCourses.length) {
        this.updatePagination(
          $includedCourses,
          this.includedCoursePage,
          ".seamless-user-dashboard-course-card",
        );
      }
    }

    /**
     * Open modal
     */
    openModal() {
      this.$modal.fadeIn(300);
      $("body").css("overflow", "hidden");
    }

    /**
     * Close modal
     */
    closeModal() {
      this.$modal.fadeOut(300);
      $("body").css("overflow", "");
      this.clearMessage();
    }

    /**
     * Submit form via AJAX
     */
    submitForm() {
      const self = this;
      const formData = this.$form.serializeArray();
      const data = {};

      // Convert form data to object
      $.each(formData, function (i, field) {
        data[field.name] = field.value;
      });

      // Validate required fields
      if (!data.first_name || !data.last_name || !data.email) {
        this.showMessage(
          "error",
          "First name, last name, and email are required.",
        );
        return;
      }

      // Show loading state
      const $submitBtn = this.$form.find('button[type="submit"]');
      const storedOriginalText = $submitBtn.data("original-text");
      const originalText = storedOriginalText || $submitBtn.text();
      if (!storedOriginalText) {
        $submitBtn.data("original-text", originalText);
      }
      $submitBtn.prop("disabled", true).text("Saving...");
      this.clearMessage();

      // Make AJAX request
      $.ajax({
        url: seamlessAddonData.ajaxUrl,
        type: "POST",
        data: {
          action: "seamless_update_user_profile",
          nonce: seamlessAddonData.nonce,
          profile_data: data,
        },
        success: function (response) {
          if (response.success) {
            self.showMessage(
              "success",
              response.data.message || "Profile updated successfully!",
            );

            // Update view mode with new data instead of reloading page
            setTimeout(function () {
              self.updateProfileView(data);
              self.exitEditMode();
            }, 1000);
          } else {
            self.showMessage(
              "error",
              response.data || "Failed to update profile.",
            );
          }
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error:", error);
          self.showMessage("error", "An error occurred. Please try again.");
        },
        complete: function () {
          // Always restore the button label/state, regardless of success/error
          $submitBtn.prop("disabled", false).text(originalText);
        },
      });
    }

    /**
     * Show message in form
     */
    showMessage(type, message) {
      const $messageDiv = this.$form.find(
        ".seamless-user-dashboard-form-message",
      );
      $messageDiv
        .removeClass(
          "seamless-user-dashboard-message-success seamless-user-dashboard-message-error",
        )
        .addClass("seamless-user-dashboard-message-" + type)
        .html(message)
        .slideDown(200);
    }

    /**
     * Clear message
     */
    clearMessage() {
      this.$form
        .find(".seamless-user-dashboard-form-message")
        .slideUp(200)
        .html("");
    }

    /**
     * Upgrade/Downgrade Modal Functionality
     */
    initUpgradeModal() {
      const self = this;
      // Use delegation for upgrade/downgrade buttons
      this.$widget.on(
        "click",
        ".seamless-user-dashboard-badge-upgrade, .seamless-user-dashboard-badge-downgrade",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          const membershipData = JSON.parse(
            $(this).attr("data-membership-data"),
          );
          const actionType = $(this).attr("data-action-type");
          self.openUpgradeModal(membershipData, actionType);
        },
      );

      // Close modal events - Delegation
      this.$widget.on(
        "click",
        "#seamless-upgrade-modal .seamless-user-dashboard-modal-close, #seamless-upgrade-modal .seamless-user-dashboard-modal-cancel",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.closeUpgradeModal();
        },
      );

      this.$widget.on(
        "click",
        "#seamless-upgrade-modal .seamless-user-dashboard-modal-overlay",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.closeUpgradeModal();
        },
      );

      // Confirm button - Delegation
      this.$widget.on(
        "click",
        "#seamless-upgrade-modal .seamless-user-dashboard-modal-upgrade",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.handleUpgradeConfirm();
        },
      );
    }

    openUpgradeModal(membershipData, actionType) {
      const $modal = $("#seamless-upgrade-modal");
      const $title = $modal.find(".seamless-user-dashboard-modal-title");
      const $confirmBtn = $modal.find(".seamless-user-dashboard-modal-upgrade");
      const $confirmText = $confirmBtn.find(
        ".seamless-user-dashboard-modal-upgrade-text",
      );

      // Store current membership data
      this.currentMembership = membershipData;
      this.actionType = actionType;

      // Set title and button text
      $title.text(
        actionType === "upgrade"
          ? "Upgrade Membership"
          : "Downgrade Membership",
      );
      $confirmText.text(
        actionType === "upgrade" ? "Upgrade Plan" : "Downgrade Plan",
      );

      // Get available plans from membership level
      const plans =
        actionType === "upgrade"
          ? membershipData.upgradable_to
          : membershipData.downgradable_to;

      // Render plans
      this.renderPlans(plans);

      // Show modal
      $modal.show();
      $("body").css("overflow", "hidden");

      // Select first plan by default
      if (plans && plans.length > 0) {
        this.selectPlan(plans[0]);
      }
    }

    closeUpgradeModal() {
      const $modal = $("#seamless-upgrade-modal");
      $modal.hide();
      $("body").css("overflow", "");
      this.currentMembership = null;
      this.selectedPlan = null;
      this.actionType = null;
    }

    renderPlans(plans) {
      const $plansList = $("#seamless-plans-list");
      $plansList.empty();
      const self = this;

      plans.forEach(function (plan) {
        const description = plan.description
          ? plan.description.replace(/<[^>]*>/g, "").substring(0, 100)
          : "";

        // Format period
        const period = plan.period || "year";
        const periodNumber = plan.period_number || 1;
        const periodText =
          periodNumber > 1 ? `${periodNumber} ${period}s` : period;

        const $planCard = $("<div>")
          .addClass("seamless-user-dashboard-plan-card")
          .attr("data-plan-id", plan.id).html(`
            <div class="seamless-user-dashboard-plan-card-header">
              <h4 class="seamless-user-dashboard-plan-card-name">${self.escapeHtml(
                plan.label,
              )}</h4>
              <span class="seamless-user-dashboard-plan-card-price">$${parseFloat(
                plan.price,
              ).toFixed(
                2,
              )}<span class="plan-period">/${periodText}</span></span>
            </div>

          `);

        $planCard.on("click", function () {
          self.selectPlan(plan);
        });

        $plansList.append($planCard);
      });
    }

    selectPlan(plan) {
      // Update selected plan
      this.selectedPlan = plan;

      console.log("Plan selected:", plan.id, plan.label);

      // Update UI
      $(".seamless-user-dashboard-plan-card").removeClass("selected");
      $(
        '.seamless-user-dashboard-plan-card[data-plan-id="' + plan.id + '"]',
      ).addClass("selected");

      // Calculate and show proration
      this.calculateAndShowProration(plan);

      // Show plan perks
      this.showPlanPerks(plan);

      // Enable confirm button
      $(".seamless-user-dashboard-modal-upgrade").prop("disabled", false);
    }

    calculateAndShowProration(newPlan) {
      const currentPlan = this.currentMembership.plan;
      const startDate = new Date(this.currentMembership.start_date);
      const expiryDate = new Date(this.currentMembership.expiry_date);
      const today = new Date();

      // Calculate days
      const totalDays = Math.ceil(
        (expiryDate - startDate) / (1000 * 60 * 60 * 24),
      );
      const usedDays = Math.ceil((today - startDate) / (1000 * 60 * 60 * 24));
      const remainingDays = totalDays - usedDays;

      // Calculate daily prices
      const oldDailyPrice = parseFloat(currentPlan.price) / totalDays;
      const newDailyPrice = parseFloat(newPlan.price) / totalDays;

      // Calculate proration
      const oldPlanCredit = oldDailyPrice * remainingDays;
      const newPlanCharge = newDailyPrice * remainingDays;

      // Add signup fee if required
      const signupFee =
        newPlan.requires_signup_fee && parseFloat(newPlan.signup_fee) > 0
          ? parseFloat(newPlan.signup_fee)
          : 0;

      const amountToPay = newPlanCharge - oldPlanCredit + signupFee;

      // Determine if this is a downgrade or upgrade
      const isDowngrade =
        parseFloat(newPlan.price) < parseFloat(currentPlan.price);
      const isUpgrade =
        parseFloat(newPlan.price) > parseFloat(currentPlan.price);

      // apply_proration_on_switch only applies to downgrades
      const prorateEnabled =
        isDowngrade &&
        (newPlan.apply_proration_on_switch == true ||
          newPlan.apply_proration_on_switch == 1);
      const isScheduledDowngrade = isDowngrade && !prorateEnabled;

      // Update UI based on transaction type
      const $prorationSection = $("#seamless-proration");
      const $scheduledSection = $("#seamless-scheduled-info");
      const $upgradeButton = $(".seamless-user-dashboard-modal-upgrade");
      const $buttonText = $upgradeButton.find(
        ".seamless-user-dashboard-modal-upgrade-text",
      );

      if (isScheduledDowngrade) {
        // Scheduled downgrade (proration disabled)
        $prorationSection.hide();
        $scheduledSection.show();

        // Format next billing date
        const expiryDate = new Date(this.currentMembership.expiry_date);
        const formattedDate = expiryDate.toLocaleDateString("en-US", {
          year: "numeric",
          month: "long",
          day: "numeric",
        });

        // Update scheduled message for downgrade
        $("#seamless-scheduled-message").html(
          `Your plan will be downgraded to <strong>${this.escapeHtml(
            newPlan.label,
          )}</strong> at your next billing cycle on <strong>${formattedDate}</strong>. You will continue to have access to your current plan benefits until then.`,
        );

        // Update button text
        $buttonText.text("Downgrade at Next Renewal");
      } else if (isUpgrade) {
        // Upgrade - always immediate with info message
        $prorationSection.show();
        $scheduledSection.show(); // Show info message for upgrades

        // Update proration details
        $prorationSection
          .find(".seamless-user-dashboard-proration-charge")
          .text("$" + newPlanCharge.toFixed(2));
        $prorationSection
          .find(".seamless-user-dashboard-proration-credit")
          .text("-$" + oldPlanCredit.toFixed(2));

        // Show signup fee if applicable
        if (signupFee > 0) {
          if (
            $prorationSection.find(".seamless-user-dashboard-proration-signup")
              .length === 0
          ) {
            $prorationSection.find(".seamless-user-dashboard-proration-total")
              .before(`
              <div class="seamless-user-dashboard-proration-item seamless-user-dashboard-proration-signup">
                <span>Signup Fee:</span>
                <span class="seamless-user-dashboard-proration-signup-amount">$${signupFee.toFixed(
                  2,
                )}</span>
              </div>
            `);
          } else {
            $prorationSection
              .find(".seamless-user-dashboard-proration-signup-amount")
              .text("$" + signupFee.toFixed(2));
          }
        } else {
          $prorationSection
            .find(".seamless-user-dashboard-proration-signup")
            .remove();
        }

        // Update total label and amount based on whether it's a refund or charge
        const $amountRow = $prorationSection
          .find(".seamless-user-dashboard-proration-amount")
          .parent();
        const $amountLabel = $amountRow.find("span:first");
        const $amountValue = $prorationSection.find(
          ".seamless-user-dashboard-proration-amount",
        );

        if (amountToPay < 0) {
          $amountLabel.text("Estimated Refund/Credit:");
          $amountValue.text("$" + Math.abs(amountToPay).toFixed(2));
        } else {
          $amountLabel.text("Amount to Pay:");
          $amountValue.text("$" + amountToPay.toFixed(2));
        }

        const remainingDaysRounded = Math.floor(remainingDays);
        $prorationSection
          .find(".seamless-user-dashboard-remaining-days")
          .text(remainingDaysRounded);

        // Show upgrade info message
        $("#seamless-scheduled-message").html(
          `All plan changes take effect immediately. You will be charged the prorated amount based on your current billing cycle.`,
        );

        $buttonText.text("Upgrade Plan");
      } else {
        // Immediate downgrade (proration enabled)
        $prorationSection.show();
        $scheduledSection.hide();

        // Update proration details - for downgrades, show credit first (positive) then charge (negative)
        $prorationSection
          .find(".seamless-user-dashboard-proration-credit")
          .text("$" + oldPlanCredit.toFixed(2));
        $prorationSection
          .find(".seamless-user-dashboard-proration-charge")
          .text("-$" + newPlanCharge.toFixed(2));

        // Show signup fee if applicable
        if (signupFee > 0) {
          if (
            $prorationSection.find(".seamless-user-dashboard-proration-signup")
              .length === 0
          ) {
            $prorationSection.find(".seamless-user-dashboard-proration-total")
              .before(`
              <div class="seamless-user-dashboard-proration-item seamless-user-dashboard-proration-signup">
                <span>Signup Fee:</span>
                <span class="seamless-user-dashboard-proration-signup-amount">$${signupFee.toFixed(
                  2,
                )}</span>
              </div>
            `);
          } else {
            $prorationSection
              .find(".seamless-user-dashboard-proration-signup-amount")
              .text("$" + signupFee.toFixed(2));
          }
        } else {
          $prorationSection
            .find(".seamless-user-dashboard-proration-signup")
            .remove();
        }

        // Update total label and amount based on whether it's a refund or charge
        const $amountRow2 = $prorationSection
          .find(".seamless-user-dashboard-proration-amount")
          .parent();
        const $amountLabel2 = $amountRow2.find("span:first");
        const $amountValue2 = $prorationSection.find(
          ".seamless-user-dashboard-proration-amount",
        );

        if (amountToPay < 0) {
          $amountLabel2.text("Estimated Refund/Credit:");
          $amountValue2.text("$" + Math.abs(amountToPay).toFixed(2));
        } else {
          $amountLabel2.text("Amount to Pay:");
          $amountValue2.text("$" + amountToPay.toFixed(2));
        }

        const remainingDaysRounded = Math.floor(remainingDays);
        $prorationSection
          .find(".seamless-user-dashboard-remaining-days")
          .text(remainingDaysRounded);

        $buttonText.text("Downgrade Current Membership");
      }

      // Store for later use
      this.prorationData = {
        oldPlanCredit: oldPlanCredit,
        newPlanCharge: newPlanCharge,
        signupFee: signupFee,
        amountToPay: amountToPay,
        remainingDays: remainingDays,
      };
    }

    showPlanPerks(plan) {
      const $perksContainer = $("#seamless-plan-perks");
      const $planName = $(".seamless-user-dashboard-selected-plan-name");
      const self = this;

      // Add "Offerings" subtitle after plan name
      $planName.html(`${plan.label} - Offerings`);

      if (!plan.content_rules || Object.keys(plan.content_rules).length === 0) {
        $perksContainer.html(
          '<p class="seamless-user-dashboard-empty-perks">No offerings listed for this plan</p>',
        );
        return;
      }

      $perksContainer.empty();

      // Add trial period if resets_trial_period is true
      if (plan.resets_trial_period && plan.trial_days > 0) {
        const $trialPerk = $("<div>").addClass(
          "seamless-user-dashboard-perk-item seamless-user-dashboard-perk-highlight",
        ).html(`
            <div class="seamless-user-dashboard-perk-icon included">🎁</div>
            <div class="seamless-user-dashboard-perk-text">
              <p class="seamless-user-dashboard-perk-value">${plan.trial_days}-Day Free Trial</p>
            </div>
          `);
        $perksContainer.append($trialPerk);
      }

      $.each(plan.content_rules, function (key, value) {
        // Determine if included or excluded
        const isIncluded =
          value &&
          value.toLowerCase() !== "no" &&
          value.toLowerCase() !== "none";
        const iconClass = isIncluded ? "included" : "excluded";

        // SVG icons for checkmark and X
        const checkmarkSVG = `
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="10" fill="#22c55e" stroke="#22c55e" stroke-width="2"/>
            <path d="M8 12L11 15L16 9" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        `;

        const xMarkSVG = `
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="10" fill="transparent" stroke="#d1d5db" stroke-width="2"/>
            <path d="M9 9L15 15M15 9L9 15" stroke="#9ca3af" stroke-width="2" stroke-linecap="round"/>
          </svg>
        `;

        const iconSVG = isIncluded ? checkmarkSVG : xMarkSVG;

        // Use value as the main display text for a cleaner look
        const itemClass = isIncluded
          ? "seamless-user-dashboard-perk-item"
          : "seamless-user-dashboard-perk-item excluded";

        const $perkItem = $("<div>").addClass(itemClass).html(`
            <div class="seamless-user-dashboard-perk-icon ${iconClass}">${iconSVG}</div>
            <div class="seamless-user-dashboard-perk-text">
              <p class="seamless-user-dashboard-perk-value">${self.escapeHtml(
                value,
              )}</p>
            </div>
          `);

        $perksContainer.append($perkItem);
      });
    }

    handleUpgradeConfirm() {
      if (!this.selectedPlan || !this.currentMembership) {
        return;
      }

      if (this.isProcessingUpgrade) {
        console.warn("Upgrade/Downgrade already in progress. Ignoring click.");
        return;
      }

      const self = this;

      // Check if current membership is cancelled
      if (this.currentMembership.status === "cancelled") {
        this.showToast(
          "You cannot upgrade or downgrade a cancelled membership. Please wait until it expires or purchase a new membership.",
          "error",
        );
        return;
      }

      // Check if user already has an active membership with the selected plan
      const allMemberships =
        window.seamlessUserDashboard?.memberships?.current || [];
      const alreadyHasPlan = allMemberships.some((membership) => {
        return (
          membership.plan?.id === this.selectedPlan.id &&
          (membership.status === "active" || membership.status === "cancelled")
        );
      });

      if (alreadyHasPlan) {
        this.showToast(
          `You already have an active ${this.selectedPlan.label}. You cannot upgrade/downgrade to a plan you already own.`,
          "error",
        );
        return;
      }

      const $confirmButton = this.$widget.find(
        "#seamless-upgrade-modal .seamless-user-dashboard-modal-upgrade",
      );
      const originalText = $confirmButton
        .find(".seamless-user-dashboard-modal-upgrade-text")
        .text();

      // Disable button and show loading state
      this.isProcessingUpgrade = true;
      $confirmButton.prop("disabled", true);
      $confirmButton
        .find(".seamless-user-dashboard-modal-upgrade-text")
        .text("Processing...");

      // Determine action and nonce based on upgrade or downgrade
      const isUpgrade = this.actionType === "upgrade";
      const action = isUpgrade
        ? "seamless_upgrade_membership"
        : "seamless_downgrade_membership";
      const nonce = isUpgrade
        ? seamlessUserDashboard.upgradeNonce
        : seamlessUserDashboard.downgradeNonce;

      // Prepare AJAX data
      const ajaxData = {
        action: action,
        nonce: nonce,
        new_plan_id: this.selectedPlan.id,
        membership_id:
          this.currentMembership.plan?.id || this.currentMembership.id, // Prioritize plan ID as per finding
        email: seamlessUserDashboard.userEmail,
      };

      console.log(
        `=== ${isUpgrade ? "UPGRADE" : "DOWNGRADE"} REQUEST DEBUG ===`,
      );
      console.log("Action Type:", this.actionType);
      console.log("AJAX Action:", action);
      console.log("Selected Plan ID (new_plan_id):", this.selectedPlan.id);
      console.log("AJAX Data being sent:", ajaxData);

      // Make AJAX request
      $.ajax({
        url: seamlessUserDashboard.ajaxUrl,
        type: "POST",
        data: ajaxData,
        success: function (response) {
          console.log("Upgrade/Downgrade Response:", response);
          const isUpgrade = self.actionType === "upgrade";
          const isDowngrade = self.actionType === "downgrade";

          if (response.success && response.data && response.data.data) {
            // For upgrades, redirect to Stripe checkout
            if (isUpgrade) {
              const stripeUrl = response.data.data.stripe_checkout_url;

              if (stripeUrl) {
                window.location.href = stripeUrl;
              } else {
                self.showToast(
                  "Error: No checkout URL received. Please try again.",
                  "error",
                );
                $confirmButton.prop("disabled", false);
                $confirmButton
                  .find(".seamless-user-dashboard-modal-upgrade-text")
                  .text(originalText);
                self.isProcessingUpgrade = false;
              }
            }
            // For downgrades, show success message and reload
            else if (isDowngrade) {
              const responseData = response.data.data;
              const isScheduled =
                responseData.scheduled == 1 || responseData.scheduled === true;
              const effectiveOn = responseData.effective_on;

              // Close modal
              $("#seamless-upgrade-modal").hide();

              if (isScheduled && effectiveOn) {
                // Scheduled downgrade
                const effectiveDate = new Date(effectiveOn);
                const formattedDate = effectiveDate.toLocaleDateString(
                  "en-US",
                  {
                    year: "numeric",
                    month: "long",
                    day: "numeric",
                  },
                );
                self.showToast(
                  `Downgrade scheduled successfully! Your membership will be downgraded to ${self.selectedPlan.label} on ${formattedDate}.`,
                  "success",
                );
              } else {
                // Immediate downgrade
                const message =
                  response.data.message ||
                  "Membership downgraded successfully! Please check your email for refund details.";
                self.showToast(message, "success");
              }

              // Reload page after 2 seconds to show updated membership
              setTimeout(function () {
                window.location.reload();
              }, 2000);
            }
          } else {
            const errorMessage =
              response.data && response.data.message
                ? response.data.message
                : "An error occurred. Please try again.";
            self.showToast("Error: " + errorMessage, "error");
            $confirmButton.prop("disabled", false);
            $confirmButton
              .find(".seamless-user-dashboard-modal-upgrade-text")
              .text(originalText);
            self.isProcessingUpgrade = false;
          }
        },
        error: function (xhr, status, error) {
          console.error("Upgrade error:", error);
          self.showToast(
            "An error occurred while processing your upgrade. Please try again.",
            "error",
          );
          $confirmButton.prop("disabled", false);
          $confirmButton
            .find(".seamless-user-dashboard-modal-upgrade-text")
            .text(originalText);
          self.isProcessingUpgrade = false;
        },
      });
    }

    /**
     * Cancel Membership Modal Functionality
     */
    initCancelModal() {
      const self = this;

      this.$widget.on(
        "click",
        ".seamless-user-dashboard-badge-cancel",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          const membershipId = $(this).attr("data-membership-id");
          const planLabel = $(this).attr("data-plan-label");
          const planPrice = $(this).attr("data-plan-price");
          const membershipData = JSON.parse(
            $(this).attr("data-membership-data"),
          );
          self.openCancelModal(
            membershipId,
            planLabel,
            planPrice,
            membershipData,
          );
        },
      );

      // Close modal events - Delegation
      this.$widget.on(
        "click",
        "#seamless-cancel-modal .seamless-user-dashboard-modal-close, #seamless-cancel-modal .seamless-user-dashboard-modal-keep",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.closeCancelModal();
        },
      );

      this.$widget.on(
        "click",
        "#seamless-cancel-modal .seamless-user-dashboard-modal-overlay",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.closeCancelModal();
        },
      );

      // Confirm cancel button - Delegation
      this.$widget.on(
        "click",
        "#seamless-cancel-modal .seamless-user-dashboard-modal-confirm-cancel",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.handleCancelConfirm();
        },
      );
    }

    openCancelModal(membershipId, planLabel, planPrice, membershipData) {
      const $modal = $("#seamless-cancel-modal");

      // Store membership data
      this.cancelMembershipId = membershipId;
      this.cancelMembershipData = membershipData;

      // console.log("Cancel Modal Data:", membershipData);

      // Extract plan and remaining days from membership data
      const plan = membershipData.plan || {};
      const remainingDays = parseFloat(membershipData.remaining_days) || 0;

      // console.log("Cancel Modal Data:", {
      //   plan,
      //   remainingDays,
      //   prorate_on_refund: plan.prorate_on_refund,
      //   prorate_by_refund: plan.prorate_by_refund,
      //   period: plan.period,
      //   period_number: plan.period_number,
      // });

      // Update modal content
      $("#seamless-cancel-plan-name").text(planLabel);

      // Check if plan is refundable
      const isRefundable = plan.refundable == 1 || plan.refundable == true;
      const prorateOnRefund = plan.prorate_on_refund == 1;
      const prorateByRefund = plan.prorate_by_refund || "";
      const planPeriod = plan.period || "year";
      const periodNumber = parseInt(plan.period_number) || 1;
      const price = parseFloat(planPrice) || 0;

      const $refundSection = $("#seamless-cancel-refund-section");
      const $periodEndSection = $("#seamless-cancel-period-end-section");
      const $confirmButton = $(".seamless-user-dashboard-modal-confirm-cancel");
      const $buttonText = $confirmButton.find(
        ".seamless-user-dashboard-modal-confirm-cancel-text",
      );

      if (!isRefundable) {
        // Non-refundable: Cancel at period end
        $refundSection.hide();
        $periodEndSection.show();

        // Format expiry date
        const expiryDate = new Date(membershipData.expiry_date);
        const formattedDate = expiryDate.toLocaleDateString("en-US", {
          year: "numeric",
          month: "long",
          day: "numeric",
        });

        $("#seamless-cancel-period-end-message").html(
          `Your membership will be canceled at the end of your current billing cycle on <strong>${formattedDate}</strong>. You will continue to have access to all your current plan benefits until then.`,
        );

        $buttonText.text("Yes, Cancel Membership");
      } else {
        // Refundable: Show refund calculation
        $refundSection.show();
        $periodEndSection.hide();

        let refundAmount = price;
        let prorationMessage = "";

        if (prorateOnRefund && remainingDays > 0) {
          if (prorateByRefund === "day") {
            // Calculate actual days in the billing period
            const startDate = new Date(membershipData.start_date);
            const expiryDate = new Date(membershipData.expiry_date);
            const currentDate = new Date();

            const totalDays = Math.ceil(
              (expiryDate - startDate) / (1000 * 60 * 60 * 24),
            );

            // Calculate used days (from start to today, inclusive)
            const usedDays = Math.ceil(
              (currentDate - startDate) / (1000 * 60 * 60 * 24),
            );

            // Remaining days (rounded down for conservative refund)
            const remainingDaysRounded = Math.floor(remainingDays);

            const dailyRate = price / totalDays;
            refundAmount = dailyRate * remainingDaysRounded;

            prorationMessage = `Prorated refund calculated. Used ${usedDays} full day${
              usedDays !== 1 ? "s" : ""
            } (including today), ${remainingDaysRounded} day${
              remainingDaysRounded !== 1 ? "s" : ""
            } remaining.<br><br>You are eligible for a refund of <strong>$${refundAmount.toFixed(
              2,
            )}</strong> for the unused portion of your plan.`;
          } else if (prorateByRefund === "month") {
            // Calculate total months based on plan period
            let totalMonths = 12; // default for year
            if (planPeriod === "month") {
              totalMonths = periodNumber;
            } else if (planPeriod === "year") {
              totalMonths = 12 * periodNumber;
            }

            // Calculate remaining months (using actual days, not assuming 30)
            const remainingMonths = Math.floor(remainingDays / 30);
            const usedMonths = Math.max(0, totalMonths - remainingMonths);
            const monthlyRate = price / totalMonths;
            refundAmount = monthlyRate * remainingMonths;

            prorationMessage = `Prorated refund calculated. Used ${usedMonths} full month${
              usedMonths !== 1 ? "s" : ""
            } (including current), ${remainingMonths} month${
              remainingMonths !== 1 ? "s" : ""
            } remaining.<br><br>You are eligible for a refund of <strong>$${refundAmount.toFixed(
              2,
            )}</strong> for the unused portion of your plan.`;
          }
        } else {
          // Non-prorated refund
          prorationMessage =
            "Refund available as per plan policy. You are eligible for a refund for this plan.";
        }

        // Update refund UI (use .html() to render HTML tags)
        $("#seamless-cancel-proration-message").html(prorationMessage);

        $buttonText.text("Request Refund");
      }

      // Show modal
      $modal.css("display", "flex");
      $("body").css("overflow", "hidden");
    }

    closeCancelModal() {
      const $modal = $("#seamless-cancel-modal");
      $modal.css("display", "none");
      $("body").css("overflow", "");

      // Clear stored data
      this.cancelMembershipId = null;
      this.cancelMembershipData = null;
    }

    handleCancelConfirm() {
      if (!this.cancelMembershipId) {
        console.error("Cancel Membership Error: No membership ID");
        return;
      }

      const self = this;
      const $confirmButton = $("#seamless-cancel-modal").find(
        ".seamless-user-dashboard-modal-confirm-cancel",
      );
      const originalText = $confirmButton.text();

      console.log("Cancel Membership Request:", {
        membershipId: this.cancelMembershipId,
        email: seamlessUserDashboard.userEmail,
        ajaxUrl: seamlessUserDashboard.ajaxUrl,
        nonce: seamlessUserDashboard.cancelNonce,
      });

      // Disable button and show loading state
      $confirmButton.prop("disabled", true).text("Cancelling...");

      $.ajax({
        url: seamlessUserDashboard.ajaxUrl,
        type: "POST",
        data: {
          action: "seamless_cancel_membership",
          nonce: seamlessUserDashboard.cancelNonce,
          membership_id: this.cancelMembershipId,
          email: seamlessUserDashboard.userEmail,
        },
        success: function (response) {
          console.log("Cancel Membership Response:", response);

          if (response.success) {
            const plan = self.cancelMembershipData?.plan || {};
            const isRefundable =
              plan.refundable == 1 || plan.refundable == true;

            if (isRefundable) {
              self.showToast(
                "Refund request has been initiated. Your membership has been cancelled successfully. Please check your email for further instructions.",
                "success",
              );
            } else {
              self.showToast(
                "Your membership cancellation has been scheduled. You will retain access until the end of your billing cycle.",
                "success",
              );
            }

            self.closeCancelModal();
            // Reload after a short delay to show the toast
            setTimeout(() => window.location.reload(), 2000);
          } else {
            const errorMessage =
              response.data && response.data.message
                ? response.data.message
                : "An error occurred. Please try again.";
            console.error("Cancel Membership Error:", errorMessage, response);
            self.showToast("Error: " + errorMessage, "error");
            $confirmButton.prop("disabled", false).text(originalText);
          }
        },
        error: function (xhr, status, error) {
          console.error("Cancel AJAX Error:", {
            xhr,
            status,
            error,
            responseText: xhr.responseText,
          });
          self.showToast(
            "An error occurred while cancelling your membership. Please try again.",
            "error",
          );
          $confirmButton.prop("disabled", false).text(originalText);
        },
      });
    }

    /**
     * Handle cancel scheduled downgrade button click
     */
    handleCancelScheduledDowngrade($button) {
      const membershipId = $button.data("membership-id");
      const orderId = $button.data("order-id");

      if (!membershipId) {
        this.showToast("Error: Missing membership ID", "error");
        return;
      }

      // Store data for later use
      this.scheduledChangeMembershipId = membershipId;
      this.scheduledChangeOrderId = orderId;

      // Open modal
      this.openCancelScheduledModal();
    }

    /**
     * Open cancel scheduled change modal
     */
    openCancelScheduledModal() {
      $("#seamless-cancel-scheduled-modal")
        .css("display", "flex")
        .hide()
        .fadeIn(200);
      $("body").css("overflow", "hidden");
    }

    /**
     * Close cancel scheduled change modal
     */
    closeCancelScheduledModal() {
      $("#seamless-cancel-scheduled-modal").fadeOut(200);
      $("body").css("overflow", "");
    }

    /**
     * Initialize cancel scheduled change modal
     */
    initCancelScheduledModal() {
      const self = this;

      // Close button - Delegation
      this.$widget.on(
        "click",
        "#seamless-cancel-scheduled-modal .seamless-user-dashboard-modal-close",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.closeCancelScheduledModal();
        },
      );

      // Overlay click - Delegation
      this.$widget.on(
        "click",
        "#seamless-cancel-scheduled-modal .seamless-user-dashboard-modal-overlay",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.closeCancelScheduledModal();
        },
      );

      // Keep schedule button - Delegation
      this.$widget.on(
        "click",
        "#seamless-cancel-scheduled-modal .seamless-user-dashboard-modal-keep-scheduled",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.closeCancelScheduledModal();
        },
      );

      // Confirm cancel button - Delegation
      this.$widget.on(
        "click",
        "#seamless-cancel-scheduled-modal .seamless-user-dashboard-modal-confirm-cancel-scheduled",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.handleCancelScheduledConfirm();
        },
      );

      // Bind Renew Button Handler - Delegation
      this.$widget.on(
        "click",
        ".seamless-user-dashboard-renew-btn",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          const $btn = $(this);
          self.handleRenewMembership($btn);
        },
      );
    }

    /**
     * Handle membership renewal
     */
    handleRenewMembership($btn) {
      const self = this;
      const planId = $btn.data("plan-id");
      const planDataStr = $btn.data("plan-data");

      if (!planId) {
        this.showToast("Error: Missing plan ID", "error");
        return;
      }

      // Parse plan data from button attribute
      let plan;
      try {
        plan =
          typeof planDataStr === "string"
            ? JSON.parse(planDataStr)
            : planDataStr;
      } catch (e) {
        console.error("Error parsing plan data:", e);
        this.showToast("Error: Could not load plan details", "error");
        return;
      }

      if (plan) {
        self.openRenewModal(plan);
      } else {
        self.showToast("Error: Could not load plan details", "error");
      }
    }

    /**
     * Open renewal modal with plan details
     */
    openRenewModal(plan) {
      const $modal = $("#seamless-renew-modal");
      this.renewPlan = plan;

      // Update modal title and plan info
      $(".seamless-user-dashboard-renew-plan-name").text(
        plan.label || plan.name,
      );

      // Format price
      const price = parseFloat(plan.price) || 0;
      const signupFee = parseFloat(plan.sign_up_fee) || 0;
      const period = plan.period || "month";
      const periodNumber = plan.period_number || 1;
      const periodText =
        periodNumber > 1 ? `${periodNumber} ${period}s` : period;

      $(".seamless-user-dashboard-renew-plan-price").html(
        `$${price.toFixed(2)}/<span class="plan-period">${periodText}</span>`,
      );

      // Check for subsequent renewal price in pricing array
      let renewalPrice = price;
      let hasSubsequentPrice = false;

      if (
        plan.pricing &&
        Array.isArray(plan.pricing) &&
        plan.pricing.length > 0
      ) {
        // Check if pricing[0] is a number (subsequent price) or an object
        const pricingData = plan.pricing[0];
        if (typeof pricingData === "number") {
          renewalPrice = parseFloat(pricingData);
          hasSubsequentPrice = true;
        } else if (typeof pricingData === "object" && pricingData.price) {
          renewalPrice = parseFloat(pricingData.price);
          hasSubsequentPrice = true;
        }
      }

      // Calculate total with renewal price
      const total = renewalPrice + signupFee;

      // Update pricing breakdown
      $(".seamless-user-dashboard-renew-charge").text(
        `$${renewalPrice.toFixed(2)}`,
      );

      // Show/hide subsequent price icon
      if (hasSubsequentPrice) {
        $("#seamless-renew-subsequent-icon").show();
      } else {
        $("#seamless-renew-subsequent-icon").hide();
      }

      $(".seamless-user-dashboard-renew-signup-fee").text(
        `$${signupFee.toFixed(2)}`,
      );
      $(".seamless-user-dashboard-renew-total").text(`$${total.toFixed(2)}`);

      // Update right panel title with plan name and "Offerings" subtitle
      $("#seamless-renew-plan-title").html(
        `${plan.label || plan.name} - Offerings`,
      );

      // Show plan features
      this.showRenewPlanPerks(plan);

      // Show modal
      $modal.show();
      $("body").css("overflow", "hidden");
    }

    /**
     * Close renewal modal
     */
    closeRenewModal() {
      const $modal = $("#seamless-renew-modal");
      $modal.hide();
      $("body").css("overflow", "");
      this.renewPlan = null;
    }

    /**
     * Show plan perks in renewal modal
     */
    showRenewPlanPerks(plan) {
      const $perksContainer = $("#seamless-renew-plan-perks");
      const self = this;

      if (!plan.content_rules || Object.keys(plan.content_rules).length === 0) {
        $perksContainer.html(
          '<p class="seamless-user-dashboard-empty-perks">No offerings listed for this plan</p>',
        );
        return;
      }

      $perksContainer.empty();

      // Add trial period if applicable
      if (plan.resets_trial_period && plan.trial_days > 0) {
        const $trialPerk = $("<div>").addClass(
          "seamless-user-dashboard-perk-item seamless-user-dashboard-perk-highlight",
        ).html(`
            <div class="seamless-user-dashboard-perk-icon included">🎁</div>
            <div class="seamless-user-dashboard-perk-text">
              <p class="seamless-user-dashboard-perk-value">${plan.trial_days}-Day Free Trial</p>
            </div>
          `);
        $perksContainer.append($trialPerk);
      }

      $.each(plan.content_rules, function (key, value) {
        const isIncluded =
          value &&
          value.toLowerCase() !== "no" &&
          value.toLowerCase() !== "none";
        const iconClass = isIncluded ? "included" : "excluded";

        const checkmarkSVG = `
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="10" fill="#22c55e" stroke="#22c55e" stroke-width="2"/>
            <path d="M8 12L11 15L16 9" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        `;

        const xMarkSVG = `
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="10" fill="transparent" stroke="#d1d5db" stroke-width="2"/>
            <path d="M9 9L15 15M15 9L9 15" stroke="#9ca3af" stroke-width="2" stroke-linecap="round"/>
          </svg>
        `;

        const iconSVG = isIncluded ? checkmarkSVG : xMarkSVG;
        const itemClass = isIncluded
          ? "seamless-user-dashboard-perk-item"
          : "seamless-user-dashboard-perk-item excluded";

        const $perkItem = $("<div>").addClass(itemClass).html(`
            <div class="seamless-user-dashboard-perk-icon ${iconClass}">${iconSVG}</div>
            <div class="seamless-user-dashboard-perk-text">
              <p class="seamless-user-dashboard-perk-value">${self.escapeHtml(value)}</p>
            </div>
          `);

        $perksContainer.append($perkItem);
      });
    }

    /**
     * Initialize renewal modal
     */
    initRenewModal() {
      const self = this;

      // Close button
      this.$widget.on(
        "click",
        "#seamless-renew-modal .seamless-user-dashboard-modal-close, #seamless-renew-modal .seamless-user-dashboard-modal-cancel",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.closeRenewModal();
        },
      );

      // Overlay click
      this.$widget.on(
        "click",
        "#seamless-renew-modal .seamless-user-dashboard-modal-overlay",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.closeRenewModal();
        },
      );

      // Confirm renew button
      this.$widget.on(
        "click",
        "#seamless-renew-modal .seamless-user-dashboard-modal-renew",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.handleRenewConfirm();
        },
      );
    }

    /**
     * Handle renewal confirmation
     */
    handleRenewConfirm() {
      if (!this.renewPlan) {
        this.showToast("Error: No plan selected", "error");
        return;
      }

      const self = this;
      const $confirmButton = $(
        "#seamless-renew-modal .seamless-user-dashboard-modal-renew",
      );
      const originalText = $confirmButton
        .find(".seamless-user-dashboard-modal-renew-text")
        .text();

      // Disable button and show loading state
      $confirmButton.prop("disabled", true);
      $confirmButton
        .find(".seamless-user-dashboard-modal-renew-text")
        .text("Processing...");

      $.ajax({
        url: seamlessUserDashboard.ajaxUrl,
        type: "POST",
        data: {
          action: "seamless_renew_membership",
          nonce: seamlessUserDashboard.renewNonce,
          plan_id: this.renewPlan.id,
          email: seamlessUserDashboard.userEmail,
        },
        success: function (response) {
          if (response.success && response.data) {
            // Check for Stripe checkout URL
            const stripeUrl =
              response.data.data?.stripe_checkout_url ||
              response.data.stripe_checkout_url ||
              response.data.data?.checkout_url ||
              response.data.checkout_url;

            if (stripeUrl) {
              window.location.href = stripeUrl;
            } else if (response.data.data?.url) {
              window.location.href = response.data.data.url;
            } else {
              self.showToast("Renewal initiated! Redirecting...", "success");
              self.closeRenewModal();
              setTimeout(() => window.location.reload(), 2000);
            }
          } else {
            const errorMsg =
              response.data && response.data.message
                ? response.data.message
                : "Renewal failed. Please try again.";
            self.showToast(errorMsg, "error");
            $confirmButton.prop("disabled", false);
            $confirmButton
              .find(".seamless-user-dashboard-modal-renew-text")
              .text(originalText);
          }
        },
        error: function (xhr, status, error) {
          console.error("Renewal Error:", error);
          self.showToast("An error occurred. Please try again.", "error");
          $confirmButton.prop("disabled", false);
          $confirmButton
            .find(".seamless-user-dashboard-modal-renew-text")
            .text(originalText);
        },
      });
    }

    /**
     * Handle confirm cancel scheduled change
     */
    handleCancelScheduledConfirm() {
      const self = this;
      const membershipId = this.scheduledChangeMembershipId;

      if (!membershipId) {
        this.showToast("Error: Missing membership ID", "error");
        return;
      }

      const $confirmButton = $(
        ".seamless-user-dashboard-modal-confirm-cancel-scheduled",
      );
      const originalText = $confirmButton
        .find(".seamless-user-dashboard-modal-confirm-cancel-scheduled-text")
        .text();

      // Disable button and show loading state
      $confirmButton.prop("disabled", true);
      $confirmButton
        .find(".seamless-user-dashboard-modal-confirm-cancel-scheduled-text")
        .text("Cancelling...");

      // Make AJAX request
      $.ajax({
        url: seamlessUserDashboard.ajaxUrl,
        type: "POST",
        data: {
          action: "seamless_cancel_scheduled_change",
          nonce: seamlessUserDashboard.cancelScheduledNonce,
          membership_id: membershipId,
          email: seamlessUserDashboard.userEmail,
        },
        success: function (response) {
          if (response.success) {
            const message =
              response.data?.message ||
              "Scheduled downgrade cancelled successfully!";
            self.showToast(message, "success");
            self.closeCancelScheduledModal();
            // Reload after a short delay to show the toast
            setTimeout(() => window.location.reload(), 2000);
          } else {
            const errorMessage =
              response.data && response.data.message
                ? response.data.message
                : "An error occurred. Please try again.";
            self.showToast("Error: " + errorMessage, "error");
            $confirmButton.prop("disabled", false);
            $confirmButton
              .find(
                ".seamless-user-dashboard-modal-confirm-cancel-scheduled-text",
              )
              .text(originalText);
          }
        },
        error: function (xhr, status, error) {
          console.error("Cancel Scheduled Downgrade Error:", error);
          self.showToast(
            "An error occurred while cancelling the scheduled downgrade. Please try again.",
            "error",
          );
          $confirmButton.prop("disabled", false);
          $confirmButton
            .find(
              ".seamless-user-dashboard-modal-confirm-cancel-scheduled-text",
            )
            .text(originalText);
        },
      });
    }

    escapeHtml(text) {
      return $("<div>").text(text).html();
    }
  }

  /**
   * Initialize all user dashboard widgets
   */
  function initUserDashboardWidgets() {
    $(".seamless-user-dashboard").each(function () {
      const widget = new UserDashboardWidget($(this));
    });
  }

  // Initialize on document ready
  $(document).ready(function () {
    initUserDashboardWidgets();
  });

  // Re-initialize after Elementor preview refresh
  $(window).on("elementor/frontend/init", function () {
    elementorFrontend.hooks.addAction(
      "frontend/element_ready/seamless-user-dashboard.default",
      function ($scope) {
        const widget = new UserDashboardWidget(
          $scope.find(".seamless-user-dashboard"),
        );
      },
    );
  });
})(jQuery);
