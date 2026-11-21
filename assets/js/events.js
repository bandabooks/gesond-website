/*
  GESOND EVENTS LIST - edit this file to update "What's on".
  Add one block per event. Dates use YYYY-MM-DD. Times use 24h "HH:MM".
  Events on or after today show under "Coming up"; older ones move to "Recent sessions" automatically.
  Only publish an event once the date and headline acts are confirmed.

  Fields:
    id        Short unique name, used in links, e.g. "pens-down-party-2026" (required)
    title     Event name shown large (required)
    series    Series name, e.g. "Saturday Linkup" (optional)
    date      "2026-12-05" (required)
    start     "21:00" (optional)
    headline  Featured act(s) or highlight (optional)
    lineup    List of DJs/acts (optional)
    mc        MC / host (optional)
    entry     e.g. "Free entrance" (optional)
    cooler    e.g. "Cooler box R50" (optional)
    genres    e.g. ["Lekompo", "Amapiano"] (optional)
    posters   List of poster images in assets/img/events/ (optional)
*/
window.GESOND_EVENTS = [
  {
    id: "pens-down-party-2026",
    title: "Pens Down Party",
    date: "2026-11-28",
    headline: "With Stance Crew Mokopane bringing their cars to Gesond",
    lineup: ["DJ Tumi", "Mash Nick", "Mr Faraz", "Quit Soul", "DJ Desai", "Essential", "DJ Chebza", "DJ Selector", "Lunga SA", "Stevovo", "Hunter", "DJ Thabang"],
    mc: "Maghezi",
    entry: "Free entrance",
    cooler: "Cooler box R50",
    posters: ["assets/img/events/2026-11-28-pens-down-party.jpg", "assets/img/events/2026-11-28-stance-crew-mokopane.jpg"]
  },
  {
    id: "saturday-linkup-10",
    title: "Saturday Linkup 10",
    series: "Saturday Linkup",
    date: "2026-11-21",
    headline: "Featuring De Paul and DJ Foster",
    lineup: ["Mr Faraz", "Quit Soul", "Mr Fantron", "DJ Thabang", "Lunga SA"],
    mc: "Maghezi feat. Senzeni SA",
    entry: "Free entrance",
    cooler: "Cooler box R50",
    posters: ["assets/img/events/2026-11-21-saturday-linkup-10.jpg"]
  },
  {
    id: "saturday-linkup-8",
    title: "Saturday Linkup 8",
    series: "Saturday Linkup",
    date: "2026-11-07",
    lineup: ["DJ Task", "DJ Essential", "Mr Faraz", "Quit Soul", "Mr Fantron", "Lunga SA", "DJ Thabang"],
    mc: "Maghezi feat. Senzeni SA",
    entry: "Free entrance",
    cooler: "Cooler box R50",
    genres: ["Lekompo", "Amapiano"]
  }
];
