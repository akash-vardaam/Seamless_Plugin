export interface AdditionalDetail {
  name: string;
  value: string;
}

export interface Venue {
  id: string;
  name: string;
  address_line_1: string;
  address_line_2: string | null;
  city: string;
  state: string;
  country: string;
  google_map_url: string;
}

export interface Category {
  id: string;
  name: string;
  slug: string;
  color: string | null;
}

export interface Event {
  id: string;
  title: string;
  slug: string;
  type: string;
  description: string;
  except: boolean;
  except_description: string;
  featured_image: string;
  additional_images: string[];
  sponsors: string[];
  virtual_meeting_link: string | null;
  start_date: string;
  end_date: string;
  formatted_start_date: string;
  formatted_end_date: string;
  status: string;
  additional_details: AdditionalDetail[];
  venue: Venue;
  categories?: Category[];
  audiences?: Audience[];
  focuses?: Focus[];
  local_chapters?: LocalChapter[];
}

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number;
  to: number;
  has_more_pages: boolean;
}

export interface EventsApiResponse {
  success: boolean;
  message: string;
  data: {
    events: Event[];
    pagination: PaginationMeta;
  };
}

export interface Audience {
  id: string;
  name: string;
  slug: string;
  color: string | null;
}

export interface Focus {
  id: string;
  name: string;
  slug: string;
  color: string | null;
}

export interface LocalChapter {
  id: string;
  name: string;
  slug: string;
  color: string | null;
}

export type ViewType = 'list' | 'grid' | 'calendar';


export type StatusFilter = 'upcoming' | 'current' | 'past';

export interface FilterState {
  search: string;
  status: StatusFilter;
  audience: string;
  focus: string;
  localChapter: string;
  year: string;
}


