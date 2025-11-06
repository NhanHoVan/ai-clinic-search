import React, { useState } from "react";
import { Input, List, Card, Typography, Spin, message } from "antd";
import { useWordPress } from "@/hooks/use-wordpress";

const { Title } = Typography;

export default function App() {
  const { config, apiFetch } = useWordPress();
  const [loading, setLoading] = useState(false);
  const [query, setQuery] = useState("");
  const [results, setResults] = useState([]);

  const handleSearch = async (value) => {
    if (!value || !value.trim()) {
      setResults([]);
      setQuery("");
      return;
    }

    setLoading(true);
    setQuery(value);

    try {
      // Gọi API WordPress thông qua apiFetch helper
      const data = await apiFetch(
        `wp/v2/posts?search=${encodeURIComponent(value)}&per_page=20`
      );
      setResults(data);
    } catch (error) {
      console.error("Search error:", error);
      message.error("An error occurred while searching. Please try again.");
      setResults([]);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ margin: "40px auto", padding: "0 20px" }}>
      <Title level={1} style={{ textAlign: "center" }}>
        🔍 AI Search (Ant Design)
      </Title>

      <Input.Search
        placeholder="Enter the name of a hospital, doctor, or location..."
        allowClear
        enterButton="Search"
        size="large"
        onSearch={handleSearch}
        loading={loading}
      />

      <div style={{ marginTop: 30 }}>
        {loading ? (
          <Spin
            size="large"
            style={{ display: "block", margin: "50px auto" }}
          />
        ) : results.length > 0 ? (
          <>
            <Typography.Text
              type="secondary"
              style={{ marginBottom: 16, display: "block" }}
            >
              Found {results.length} results for "{query}"
            </Typography.Text>
            <List
              grid={{ gutter: 16, column: 1 }}
              dataSource={results}
              renderItem={(item) => (
                <List.Item>
                  <Card
                    title={
                      <a
                        href={item.link}
                        target="_blank"
                        dangerouslySetInnerHTML={{
                          __html: item.title.rendered,
                        }}
                      />
                    }
                    hoverable
                  >
                    <div
                      dangerouslySetInnerHTML={{
                        __html: item.excerpt.rendered,
                      }}
                    />
                  </Card>
                </List.Item>
              )}
            />
          </>
        ) : query ? (
          <Card>
            <Typography.Text type="secondary">
              No results found for "{query}"
            </Typography.Text>
          </Card>
        ) : null}
      </div>
    </div>
  );
}
