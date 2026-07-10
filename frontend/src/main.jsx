import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { SWRConfig } from "swr";
import { api } from "./lib/api";
import "./index.css";
import App from "./App.jsx";

createRoot(document.getElementById("root")).render(
  <StrictMode>
    <SWRConfig
      value={{
        fetcher: (url) => api(url),
        revalidateOnFocus: true, // Auto refetch when window is focused
        shouldRetryOnError: false,
      }}
    >
      <App />
    </SWRConfig>
  </StrictMode>
);
