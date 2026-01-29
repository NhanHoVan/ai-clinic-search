import { useState, useEffect, useCallback, useRef } from "react";
import { Typography, message, Row, Col, Pagination } from "antd";
import { normalizeText } from "@/utils/utils";
import { useWordPress } from "@/hooks/use-wordpress";
import { useGeoLocation } from "@/hooks/use-geo-location";
import { useLocationPreference } from "@/hooks/use-location-preference";
import ClinicCard from "@/component/clinic-card.jsx";
import SearchHeader from "@/component/search-header.jsx";
import EditClinicModal from "@/component/edit-clinic-modal.jsx";
import ClinicContactContent from "@/component/clinic-contact-content.jsx";
import ClinicSearchList from "@/component/clinic-search-list.jsx";
import SpecialtyTags from "@/component/specialty-tags";

const { Title } = Typography;

export default function App() {
	const { apiFetch } = useWordPress();
	const { getRegionName } = useGeoLocation();
	const abortControllerRef = useRef(null);

	const [searchText, setSearchText] = useState("");
	const [loadingSearch, setLoadingSearch] = useState(false);
	const [clinicsSearch, setClinicsSearch] = useState([]);
	const [hasSearched, setHasSearched] = useState(false);

	const [page, setPage] = useState(1);
	const [limit] = useState(8);
	const [total, setTotal] = useState(0);
	const [clinicsRefer, setClinicsRefer] = useState([]);
	const [loadingRefer, setLoadingRefer] = useState(false);

	const [provinces, setProvinces] = useState([]);
	const [districts, setDistricts] = useState([]);
	const { pref, setProvince, setDistrict } = useLocationPreference();
	const [selectedProvince, setSelectedProvince] = useState();
	const [selectedDistrict, setSelectedDistrict] = useState();

	const [specialties, setSpecialties] = useState([]);
	const [selectedSpecialtyIds, setSelectedSpecialtyIds] = useState([]);
	const [loadingSpecialties, setLoadingSpecialties] = useState(false);

	const [openModel, setOpenModel] = useState(false);
	const [selectedClinic, setSelectedClinic] = useState(null);

	const fetchProvinces = useCallback(
		async (search = "") => {
			const data = await apiFetch("/provinces", {
				method: "GET",
				params: search ? { search } : {},
			});
			setProvinces(Array.isArray(data) ? data : []);
		},
		[apiFetch],
	);

	const fetchDistricts = useCallback(
		async (search = "") => {
			if (!selectedProvince) {
				throw new Error("Missing required parameters");
			}

			const data = await apiFetch("/districts", {
				body: {
					province_code: selectedProvince,
					...(search ? { search } : {}),
				},
			});
			setDistricts(Array.isArray(data) ? data : []);
		},
		[apiFetch, selectedProvince],
	);

	const fetchClinicsSearch = useCallback(
		async (search = "") => {
			if (!search || !selectedProvince || !selectedDistrict) {
				throw new Error("Missing required parameters");
			}

			if (abortControllerRef.current) {
				abortControllerRef.current.abort();
			}

			abortControllerRef.current = new AbortController();

			try {
				const data = await apiFetch("/clinics/search", {
					body: {
						search,
						province: selectedProvince,
						district: selectedDistrict,
						specialties: selectedSpecialtyIds,
					},
					signal: abortControllerRef.current.signal,
				});
				setClinicsSearch(data.clinics);
			} catch (error) {
				if (error.name !== "AbortError") {
					throw error;
				}
			}
		},
		[apiFetch, selectedProvince, selectedDistrict, selectedSpecialtyIds],
	);

	const fetchClinicsRefer = useCallback(
		async (page = 1) => {
			const data = await apiFetch("/clinics", {
				body: {
					province: selectedProvince,
					district: selectedDistrict,
					specialties: selectedSpecialtyIds,
					page,
					limit,
				},
			});

			setClinicsRefer(data.clinics);
			setTotal(data.total || 0);
			setPage(data.page || page);
		},
		[apiFetch, selectedProvince, selectedDistrict, selectedSpecialtyIds],
	);

	const handleClinicsRefer = async (page) => {
		setLoadingRefer(true);

		try {
			await fetchClinicsRefer(page);
		} catch (e) {
			message.error(e?.message);
		} finally {
			setLoadingRefer(false);
		}
	};

	useEffect(() => {
		handleClinicsRefer(1);
	}, [selectedProvince, selectedDistrict, selectedSpecialtyIds]);

	useEffect(() => {
		fetchProvinces().catch((e) => message.error(e?.message));
	}, [fetchProvinces]);

	useEffect(() => {
		if (!provinces.length || selectedProvince) return;

		if (pref.province) {
			setSelectedProvince(pref.province);
			return;
		}

		async function detect() {
			const regionName = await getRegionName();
			if (!regionName) return;

			const normRegion = normalizeText(regionName);

			const found = provinces.find((p) =>
				normalizeText(p.name).includes(normRegion),
			);

			if (found) {
				setSelectedProvince(found.code);
			}
		}

		detect();
	}, [provinces, getRegionName]);

	useEffect(() => {
		if (pref.district) {
			setSelectedDistrict(pref.district);
		}
	}, [districts]);

	useEffect(() => {
		if (!selectedProvince) {
			setDistricts([]);
			setDistrict(null);
			setSelectedDistrict("");
			return;
		}

		fetchDistricts().catch((e) => message.error(e?.message));
	}, [selectedProvince, fetchDistricts]);

	useEffect(() => {
		return () => {
			if (abortControllerRef.current) {
				abortControllerRef.current.abort();
			}
		};
	}, []);

	const handleClinicsSearch = async (search) => {
		setSearchText(search);

		const isEmptySearch = !search?.trim();
		const hasFilters = selectedProvince || selectedDistrict;

		if (isEmptySearch && !hasFilters) {
			setClinicsSearch([]);
			return;
		}

		setLoadingSearch(true);

		try {
			await fetchClinicsSearch(search);
			setHasSearched(true);
		} catch (e) {
			if (e?.name !== "AbortError") {
				message.error(e?.message);
				setClinicsSearch([]);
			}
		} finally {
			setLoadingSearch(false);
		}
	};

	const handleProvinceChange = (value) => {
		setSelectedProvince(value);
		setProvince(value);
		setSelectedDistrict("");
		setDistricts([]);
		setSearchText("");
		setHasSearched(false);
	};

	const handleDistrictChange = (value) => {
		setSelectedDistrict(value);
		setDistrict(value);
		setSearchText("");
		setHasSearched(false);
	};

	const handleChangeText = (value) => {
		setSearchText(value);
		if (value === "") {
			// setHasSearched(false);
		}
	};

	const handleOpenLink = (link) => {
		if (typeof link !== "string") return;

		const trimmed = link.trim();
		if (!trimmed) return;

		const url = /^https?:\/\//i.test(trimmed)
			? trimmed
			: `https://${trimmed}`;

		window.open(url, "_blank", "noopener,noreferrer");
	};

	const handleDetailClinic = (clinicId) => {
		const clinic = clinicsRefer.find(
			(c) => String(c.id) === String(clinicId),
		);
		// TODO call getClinicCard API
		setSelectedClinic(clinic);
		setOpenModel(true);
	};

	const fetchSpecialties = useCallback(async () => {
		const data = await apiFetch("/specialties", {
			method: "GET",
		});
		setSpecialties(
			Array.isArray(data?.specialties) ? data.specialties : [],
		);
	}, [apiFetch]);

	const handleSpecialties = async () => {
		setLoadingSpecialties(true);
		try {
			await fetchSpecialties();
		} catch (e) {
			message.error(e?.message);
		} finally {
			setLoadingSpecialties(false);
		}
	};

	useEffect(() => {
		handleSpecialties();
	}, []);

	const handleSpecialtyChange = (id) => {
		setSelectedSpecialtyIds((prev) =>
			prev.includes(id) ? prev.filter((c) => c !== id) : [...prev, id],
		);
	};

	return (
		<div style={{ margin: "20px auto" }}>
			<Title level={2} style={{ textAlign: "center", marginBottom: 20 }}>
				Tìm kiếm phòng khám, bệnh viện gần bạn
			</Title>

			<SearchHeader
				provinces={provinces}
				districts={districts}
				selectedProvince={selectedProvince}
				selectedDistrict={selectedDistrict}
				searchText={searchText}
				loading={loadingSearch}
				handleProvinceChange={handleProvinceChange}
				handleDistrictChange={handleDistrictChange}
				setSearchText={handleChangeText}
				handleSearch={handleClinicsSearch}
			/>

			<SpecialtyTags
				specialties={specialties}
				selectedSpecialtyIds={selectedSpecialtyIds}
				loading={loadingSpecialties}
				onChange={handleSpecialtyChange}
			/>

			<ClinicSearchList
				clinics={clinicsSearch}
				searchText={searchText}
				loading={loadingSearch}
				hasSearched={hasSearched}
			/>

			{clinicsRefer.length > 0 ? (
				<div style={{ marginTop: 30 }}>
					<Title
						level={3}
						style={{ textAlign: "left", marginBottom: 20 }}
					>
						Tham khảo các địa chỉ khác:
					</Title>
					<Row gutter={[16, 16]}>
						{clinicsRefer.map((clinic, index) => {
							return (
								<Col key={index} xs={24} sm={12} md={12} lg={6}>
									<ClinicCard
										clinic={clinic}
										loading={loadingRefer}
										onOpenLink={
											clinic.website
												? handleOpenLink
												: null
										}
										onViewDetail={handleDetailClinic}
									/>
								</Col>
							);
						})}
					</Row>
					<Pagination
						style={{ marginTop: 20 }}
						align="center"
						current={page}
						pageSize={limit}
						total={total}
						onChange={(p) => handleClinicsRefer(p)}
					/>
				</div>
			) : null}

			<EditClinicModal
				open={openModel}
				clinic={selectedClinic}
				onCancel={() => {
					setOpenModel(false);
					setSelectedClinic(null);
				}}
				title=""
				showFooter={false}
				showCloseIcon={true}
				renderForm={() => (
					<ClinicContactContent clinic={selectedClinic} />
				)}
			/>
		</div>
	);
}
