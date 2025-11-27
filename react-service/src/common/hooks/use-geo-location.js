export function useGeoLocation() {
  const getRegionName = async () => {
    try {
      const res = await fetch("http://ip-api.com/json/");
      const data = await res.json();

      if (data.status === "success") {
        return data.regionName;
      }
    } catch (e) {
      console.error(e);
    }
    return null;
  };

  return { getRegionName };
}
