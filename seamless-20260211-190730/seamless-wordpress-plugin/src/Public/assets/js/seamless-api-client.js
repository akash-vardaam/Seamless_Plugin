/**
 * Seamless Direct API Fetcher
 */

class SeamlessDirectAPI {
  constructor() {
    // Get API domain and remove trailing slash to prevent double slashes
    this.apiDomain = (window.seamless_ajax?.api_domain || "").replace(
      /\/+$/,
      "",
    );
    this.cache = new Map();
    this.cacheTimeout = 60000;

    if (!this.apiDomain) {
      console.error("[Seamless API] ERROR: API domain is not configured!");
      console.error(
        "[Seamless API] Please set the Client Domain in WordPress Admin → Seamless → Authentication",
      );
      console.error(
        "[Seamless API] Current seamless_ajax object:",
        window.seamless_ajax,
      );
    } else {
      // console.log("[Seamless API] Initialized with domain:", this.apiDomain);
    }
  }

  /**
   * Get cache key for a request
   */
  getCacheKey(endpoint, params = {}) {
    const paramString = new URLSearchParams(params).toString();
    return `${endpoint}?${paramString}`;
  }

  /**
   * Check if cached data is still valid
   */
  isCacheValid(cacheEntry) {
    if (!cacheEntry) return false;
    return Date.now() - cacheEntry.timestamp < this.cacheTimeout;
  }

  /**
   * Fetch data from API with request-level caching
   */
  async fetch(endpoint, params = {}, useCache = true) {
    if (!this.apiDomain) {
      const error = new Error(
        "API domain is not configured. Please set it in WordPress Admin → Seamless → Authentication",
      );
      console.error("[Seamless API]", error.message);
      throw error;
    }

    const cacheKey = this.getCacheKey(endpoint, params);

    if (useCache && this.cache.has(cacheKey)) {
      const cached = this.cache.get(cacheKey);
      if (this.isCacheValid(cached)) {
        // console.log(`[Seamless] Using cached data for: ${endpoint}`);
        return cached.data;
      }
    }

    try {
      const url = new URL(`${this.apiDomain}/api/${endpoint}`);

      Object.keys(params).forEach((key) => {
        if (
          params[key] !== null &&
          params[key] !== undefined &&
          params[key] !== ""
        ) {
          url.searchParams.append(key, params[key]);
        }
      });

      const response = await fetch(url.toString(), {
        method: "GET",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();
      if (useCache) {
        this.cache.set(cacheKey, {
          data: result,
          timestamp: Date.now(),
        });
      }

      return result;
    } catch (error) {
      console.error(`[Seamless] API Error (${endpoint}):`, error);
      throw error;
    }
  }

  /**
   * Clear all cached data
   */
  clearCache() {
    this.cache.clear();
    console.log("[Seamless] Cache cleared");
  }

  /**
   * Clear specific cache entry
   */
  clearCacheEntry(endpoint, params = {}) {
    const cacheKey = this.getCacheKey(endpoint, params);
    this.cache.delete(cacheKey);
  }

  /**
   * Get all events with pagination
   */
  async getEvents(
    page = 1,
    categories = [],
    search = "",
    sort = "all",
    perPage = 15,
  ) {
    const params = {
      page,
      search,
      per_page: perPage,
    };

    if (categories && categories.length > 0) {
      params.category_ids = categories.join(",");
    }

    if (sort !== "all") {
      params.status = sort;
    }

    return await this.fetch("events", params);
  }

  /**
   * Get all group events with pagination
   */
  async getGroupEvents(page = 1, categories = [], search = "", sort = "all") {
    const params = {
      page,
      search,
    };

    if (categories && categories.length > 0) {
      params.category_ids = categories.join(",");
    }

    if (sort !== "all") {
      params.status = sort;
    }

    return await this.fetch("group-events", params);
  }

  /**
   * Get single event by slug
   */
  async getEvent(slug) {
    return await this.fetch(`events/${slug}`);
  }

  /**
   * Get single group event by slug
   * Also fetches full data for associated events to get their schedules
   */
  async getGroupEvent(slug) {
    const groupEventResponse = await this.fetch(`group-events/${slug}`);

    // If the group event has associated events, fetch their full data including schedules
    if (
      groupEventResponse?.data?.associated_events &&
      Array.isArray(groupEventResponse.data.associated_events)
    ) {
      const associatedEventsWithSchedules = await Promise.all(
        groupEventResponse.data.associated_events.map(
          async (associatedEvent) => {
            // If the associated event has a slug, fetch its full data
            if (associatedEvent.slug) {
              try {
                const fullEventResponse = await this.fetch(
                  `events/${associatedEvent.slug}`,
                );
                if (fullEventResponse?.data) {
                  // Merge the full event data (including schedules) with the associated event
                  return {
                    ...associatedEvent,
                    ...fullEventResponse.data,
                    schedules: fullEventResponse.data.schedules || [],
                  };
                }
              } catch (error) {
                console.warn(
                  `[Seamless] Could not fetch full data for associated event: ${associatedEvent.slug}`,
                  error,
                );
              }
            }
            // Return the original associated event if we couldn't fetch full data
            return associatedEvent;
          },
        ),
      );

      // Update the group event with the enhanced associated events
      groupEventResponse.data.associated_events = associatedEventsWithSchedules;
    }

    return groupEventResponse;
  }

  /**
   * Get event by slug - automatically detects if it's a regular event or group event
   * Uses localStorage to remember event types for faster subsequent loads
   */
  async getEventBySlug(slug) {
    const cachedType = this.getEventTypeFromStorage(slug);

    if (cachedType === "group_event") {
      try {
        const result = await this.getGroupEvent(slug);
        if (result && result.data) {
          return {
            ...result,
            data: {
              ...result.data,
              event_type: "group_event",
            },
          };
        }
      } catch (error) {
        this.removeEventTypeFromStorage(slug);
      }
    }
    try {
      const result = await this.fetchSilent(`events/${slug}`);
      if (result && result.data) {
        this.saveEventTypeToStorage(slug, "event");
        return {
          ...result,
          data: {
            ...result.data,
            event_type: "event",
          },
        };
      }
    } catch (error) {}

    try {
      const result = await this.getGroupEvent(slug);
      if (result && result.data) {
        this.saveEventTypeToStorage(slug, "group_event");
        return {
          ...result,
          data: {
            ...result.data,
            event_type: "group_event",
          },
        };
      }
    } catch (error) {
      console.error(
        `[Seamless] Event '${slug}' not found in either events or group-events endpoints`,
      );
      throw new Error(`Event '${slug}' not found`);
    }
  }

  getEventTypeFromStorage(slug) {
    try {
      const stored = localStorage.getItem("seamless_event_types");
      if (stored) {
        const types = JSON.parse(stored);
        return types[slug] || null;
      }
    } catch (e) {}
    return null;
  }

  /**
   * Save event type to localStorage
   */
  saveEventTypeToStorage(slug, type) {
    try {
      const stored = localStorage.getItem("seamless_event_types");
      const types = stored ? JSON.parse(stored) : {};
      types[slug] = type;

      const entries = Object.entries(types);
      if (entries.length > 100) {
        const reduced = Object.fromEntries(entries.slice(-100));
        localStorage.setItem("seamless_event_types", JSON.stringify(reduced));
      } else {
        localStorage.setItem("seamless_event_types", JSON.stringify(types));
      }
    } catch (e) {}
  }

  /**
   * Remove event type from localStorage
   */
  removeEventTypeFromStorage(slug) {
    try {
      const stored = localStorage.getItem("seamless_event_types");
      if (stored) {
        const types = JSON.parse(stored);
        delete types[slug];
        localStorage.setItem("seamless_event_types", JSON.stringify(types));
      }
    } catch (e) {}
  }

  /**
   * Fetch data from API without logging errors (for silent fallback attempts)
   */
  async fetchSilent(endpoint, params = {}) {
    if (!this.apiDomain) {
      throw new Error("API domain not configured");
    }

    const cacheKey = this.getCacheKey(endpoint, params);

    if (this.cache.has(cacheKey)) {
      const cached = this.cache.get(cacheKey);
      if (this.isCacheValid(cached)) {
        return cached.data;
      }
    }

    const url = new URL(`${this.apiDomain}/api/${endpoint}`);

    Object.keys(params).forEach((key) => {
      if (
        params[key] !== null &&
        params[key] !== undefined &&
        params[key] !== ""
      ) {
        url.searchParams.append(key, params[key]);
      }
    });

    const response = await fetch(url.toString(), {
      method: "GET",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();
    this.cache.set(cacheKey, {
      data: result,
      timestamp: Date.now(),
    });

    return result;
  }

  /**
   * Get event categories
   */
  async getCategories() {
    return await this.fetch("categories");
  }

  /**
   * Fetch all events (both regular and group events) across all pages
   */
  async fetchAllEvents(categories = [], search = "") {
    const allEvents = [];
    let page = 1;
    let hasMoreEvents = true;
    let hasMoreGroupEvents = true;

    while (hasMoreEvents) {
      try {
        const result = await this.getEvents(
          page,
          categories,
          search,
          "all",
          100,
        );

        if (
          result.data &&
          result.data.events &&
          result.data.events.length > 0
        ) {
          const eventsWithType = result.data.events.map((event) => ({
            ...event,
            event_type: "event",
          }));
          allEvents.push(...eventsWithType);

          hasMoreEvents = result.data.pagination?.has_more_pages || false;
        } else {
          hasMoreEvents = false;
        }

        page++;

        if (page > 50) {
          console.warn("[Seamless] Event fetch exceeded 50 pages. Breaking.");
          break;
        }
      } catch (error) {
        console.error("[Seamless] Error fetching events:", error);
        hasMoreEvents = false;
      }
    }

    page = 1;
    while (hasMoreGroupEvents) {
      try {
        const result = await this.getGroupEvents(
          page,
          categories,
          search,
          "all",
        );

        if (
          result.data &&
          result.data.group_events &&
          result.data.group_events.length > 0
        ) {
          const filteredGroupEvents = result.data.group_events
            .filter(
              (event) =>
                event.associated_events && event.associated_events.length > 0,
            )
            .map((event) => ({
              ...event,
              event_type: "group_event",
            }));

          allEvents.push(...filteredGroupEvents);

          hasMoreGroupEvents = result.data.pagination?.has_more_pages || false;
        } else {
          hasMoreGroupEvents = false;
        }

        page++;

        if (page > 50) {
          console.warn(
            "[Seamless] Group event fetch exceeded 50 pages. Breaking.",
          );
          break;
        }
      } catch (error) {
        console.error("[Seamless] Error fetching group events:", error);
        hasMoreGroupEvents = false;
      }
    }

    const publishedEvents = allEvents.filter((event) => {
      const status = (event.status || "").toLowerCase();
      return status === "published";
    });

    this.cacheEventTypes(publishedEvents);

    return publishedEvents;
  }

  /**
   * Cache event types in localStorage for faster single event loading
   */
  cacheEventTypes(events) {
    try {
      const stored = localStorage.getItem("seamless_event_types");
      const types = stored ? JSON.parse(stored) : {};

      events.forEach((event) => {
        if (event.slug && event.event_type) {
          types[event.slug] = event.event_type;
        }
      });

      const entries = Object.entries(types);
      if (entries.length > 100) {
        const reduced = Object.fromEntries(entries.slice(-100));
        localStorage.setItem("seamless_event_types", JSON.stringify(reduced));
      } else {
        localStorage.setItem("seamless_event_types", JSON.stringify(types));
      }
    } catch (e) {}
  }

  // ============ Membership API Methods ============

  /**
   * Get membership plans
   */
  async getMembershipPlans(page = 1, search = "") {
    const params = {
      page,
      search,
    };

    return await this.fetch("membership-plans", params);
  }

  /**
   * Get single membership plan
   */
  async getMembershipPlan(planId) {
    return await this.fetch(`membership-plans/${planId}`);
  }

  /**
   * Get all membership plans with details
   */
  async getAllMembershipPlans() {
    try {
      const result = await this.getMembershipPlans(1, "");

      if (!result.data || !Array.isArray(result.data)) {
        return [];
      }

      const plansWithDetails = await Promise.all(
        result.data.map(async (plan) => {
          if (plan.id) {
            try {
              const detailResult = await this.getMembershipPlan(plan.id);
              if (detailResult.data) {
                return { ...plan, ...detailResult.data };
              }
            } catch (error) {
              console.error(
                `[Seamless] Error fetching plan ${plan.id}:`,
                error,
              );
            }
          }
          return plan;
        }),
      );

      return plansWithDetails;
    } catch (error) {
      console.error("[Seamless] Error fetching membership plans:", error);
      return [];
    }
  }

  // ============ Courses API Methods ============

  /**
   * Get courses
   */
  async getCourses(page = 1, search = "") {
    const params = {
      page,
      search,
    };

    return await this.fetch("courses", params);
  }

  /**
   * Get single course by ID
   */
  async getCourse(courseId) {
    return await this.fetch(`courses/${courseId}`);
  }

  /**
   * Get all courses with details
   */
  async getAllCourses() {
    try {
      const result = await this.getCourses(1, "");

      if (!result.data || !Array.isArray(result.data)) {
        return [];
      }

      return result.data;
    } catch (error) {
      console.error("[Seamless] Error fetching courses:", error);
      return [];
    }
  }
}

window.SeamlessAPI = new SeamlessDirectAPI();

// Export for module usage
if (typeof module !== "undefined" && module.exports) {
  module.exports = SeamlessDirectAPI;
}
